<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CameraDevice;
use App\Models\CameraOwnerKey;
use App\Models\CameraViewSession;
use App\Services\CameraAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CameraController extends Controller
{
    public function index(Request $request, CameraAccess $access)
    {
        $unlocked = $access->unlocked($request);

        return response()->json([
            'authenticator_required' => (bool) config('camera.require_authenticator'),
            'authenticator_ready' => CameraOwnerKey::where('user_id', $request->user()->id)->whereNotNull('confirmed_at')->exists(),
            'unlocked' => $unlocked, 'turn_ready' => $access->turnReady(),
            'devices' => CameraDevice::where('owner_id', $request->user()->id)->latest()->get()->map(fn ($device) => [
                'id' => $device->id, 'label' => $device->label, 'family_account_id' => $device->family_account_id,
                'paired' => (bool) $device->paired_at, 'revoked' => (bool) $device->revoked_at,
                'online' => ! $device->revoked_at && $device->last_seen_at?->gt(now()->subSeconds(config('camera.online_seconds'))),
                'armed' => $device->armed, 'camera_available' => $device->camera_available,
                'state' => $device->agent_state, 'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'busy' => $access->busy($device),
            ]),
        ])->header('Cache-Control', 'no-store');
    }

    public function create(Request $request, CameraAccess $access)
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'family_account_id' => ['required', 'integer', Rule::exists('family_accounts', 'id')->where('device_type', 'tv')],
        ]);
        $code = strtoupper(bin2hex(random_bytes(8)));
        $device = CameraDevice::create($data + ['owner_id' => $request->user()->id,
            'pair_code_hash' => hash('sha256', $code), 'pair_expires_at' => now()->addMinutes(10)]);
        $access->audit('camera.pairing.created', $device);

        return response()->json(['device_id' => $device->id, 'pair_code' => $code, 'expires_at' => $device->pair_expires_at], 201);
    }

    public function revoke(Request $request, CameraDevice $device, CameraAccess $access)
    {
        abort_unless($device->owner_id === $request->user()->id, 404);
        DB::transaction(function () use ($device, $access) {
            $device = CameraDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();
            $device->update(['revoked_at' => now(), 'token_hash' => null, 'pair_code_hash' => null, 'armed' => false]);
            foreach ($device->sessions()->whereNull('ended_at')->lockForUpdate()->get() as $session) {
                $access->end($session, 'revoked');
            }
            $access->audit('camera.revoked', $device);
        });

        return response()->noContent();
    }

    public function ice(Request $request, CameraAccess $access)
    {
        return ['ice_servers' => $access->iceServers('viewer-'.$access->viewerHash($request)),
            'turn_ready' => $access->turnReady()];
    }

    public function start(Request $request, CameraDevice $device, CameraAccess $access)
    {
        abort_unless($device->owner_id === $request->user()->id, 404);
        $data = $request->validate(['offer' => 'required|array:type,sdp',
            'offer.type' => 'required|in:offer', 'offer.sdp' => 'required|string|max:100000']);

        return DB::transaction(function () use ($request, $device, $access, $data) {
            $device = CameraDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();
            abort_if($device->revoked_at || ! $device->paired_at, 409, 'Pair this camera again.');
            abort_unless($device->armed && $device->last_seen_at?->gt(now()->subSeconds(config('camera.online_seconds'))), 409, 'Open the camera app on the TV and enable remote access.');
            abort_unless($device->camera_available, 409, 'The USB camera is unavailable.');
            abort_if($access->busy($device), 409, 'FamilyCall is using this TV. End the call before viewing.');
            foreach ($device->sessions()->whereNull('ended_at')->lockForUpdate()->get() as $old) {
                $access->expire($old, $device);
                if (! $old->ended_at) {
                    return response()->json(['message' => 'This camera is already being viewed.'], 409);
                }
            }
            $session = $device->sessions()->create(['owner_id' => $request->user()->id,
                'viewer_hash' => $access->viewerHash($request), 'offer' => $data['offer'],
                'viewer_seen_at' => now(), 'expires_at' => now()->addSeconds(config('camera.session_seconds'))]);
            $access->audit('camera.view.requested', $device, ['session_id' => $session->id]);

            return response()->json(['session' => $session->only(['id', 'status', 'expires_at'])], 201);
        });
    }

    private function withViewer(Request $request, string $id, CameraAccess $access, callable $callback)
    {
        $session = CameraViewSession::whereKey($id)->where('owner_id', $request->user()->id)
            ->where('viewer_hash', $access->viewerHash($request))->firstOrFail();

        return DB::transaction(function () use ($session, $access, $callback) {
            $device = CameraDevice::whereKey($session->camera_device_id)->lockForUpdate()->firstOrFail();
            $session = CameraViewSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $access->expire($session, $device);

            return $callback($session);
        });
    }

    public function show(Request $request, string $id, CameraAccess $access)
    {
        $request->validate(['after' => 'sometimes|integer|min:0']);

        return $this->withViewer($request, $id, $access, fn ($session) => [
            'session' => $session->only(['id', 'status', 'answer', 'expires_at', 'end_reason']),
            'signals' => $session->signals()->where('sender', 'agent')->where('id', '>', (int) $request->query('after', 0))->orderBy('id')->limit(100)->get(['id', 'payload']),
        ]);
    }

    public function heartbeat(Request $request, string $id, CameraAccess $access)
    {
        return $this->withViewer($request, $id, $access, function ($session) {
            if ($session->ended_at) {
                return response()->json(['message' => 'Viewing session ended.'], 409);
            }
            $session->update(['viewer_seen_at' => now()]);

            return ['ok' => true];
        });
    }

    public function signal(Request $request, string $id, CameraAccess $access)
    {
        return $this->withViewer($request, $id, $access, function ($session) use ($request, $access) {
            if ($session->ended_at) {
                return response()->json(['message' => 'Viewing session ended.'], 409);
            }
            $access->candidates($request, $session, 'viewer');

            return ['ok' => true];
        });
    }

    public function end(Request $request, string $id, CameraAccess $access)
    {
        return $this->withViewer($request, $id, $access, function ($session) use ($access) {
            $access->end($session, 'owner_stopped');

            return ['ok' => true];
        });
    }
}
