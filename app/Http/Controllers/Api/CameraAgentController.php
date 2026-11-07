<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CameraDevice;
use App\Models\CameraViewSession;
use App\Services\CameraAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CameraAgentController extends Controller
{
    public function pair(Request $request, CameraAccess $access)
    {
        $data = $request->validate(['code' => ['required', 'regex:/^[A-Fa-f0-9]{16}$/D']]);

        return DB::transaction(function () use ($data, $access) {
            $device = CameraDevice::where('pair_code_hash', hash('sha256', strtoupper($data['code'])))->lockForUpdate()->first();
            abort_unless($device && ! $device->revoked_at && ! $device->paired_at && $device->pair_expires_at?->gt(now()), 422, 'Pairing code is invalid or expired.');
            $token = bin2hex(random_bytes(32));
            $device->update(['token_hash' => hash('sha256', $token), 'pair_code_hash' => null,
                'pair_expires_at' => null, 'paired_at' => now(), 'agent_state' => 'disabled']);
            $access->audit('camera.paired', $device);

            return response()->json(['id' => $device->id, 'token' => $token, 'label' => $device->label], 201)->header('Cache-Control', 'no-store');
        });
    }

    private function device(Request $request): CameraDevice
    {
        $device = CameraDevice::whereKey($request->attributes->get('cameraDevice')->id)->lockForUpdate()->firstOrFail();
        abort_if($device->revoked_at || ! hash_equals((string) $device->token_hash, hash('sha256', (string) $request->bearerToken())), 401);

        return $device;
    }

    public function poll(Request $request, CameraAccess $access)
    {
        $data = $request->validate(['armed' => 'required|boolean', 'camera_available' => 'required|boolean',
            'state' => 'required|in:ready,viewing,disabled,camera_missing,camera_busy',
            'session_id' => 'nullable|uuid', 'after' => 'sometimes|integer|min:0']);

        return DB::transaction(function () use ($request, $access, $data) {
            $device = $this->device($request);
            $device->update(['armed' => $data['armed'], 'camera_available' => $data['camera_available'],
                'agent_state' => $data['state'], 'last_seen_at' => now()]);
            $session = $device->sessions()->whereNull('ended_at')->lockForUpdate()->first();
            if ($session) {
                $access->expire($session, $device);
            }
            if (! $session || $session->ended_at) {
                return ['session' => null, 'busy' => $access->busy($device)];
            }
            $after = ($data['session_id'] ?? null) === $session->id ? ($data['after'] ?? 0) : 0;

            return [
                'session' => ['id' => $session->id, 'offer' => $session->offer, 'status' => $session->status,
                    'lease_seconds' => max(0, min($session->expires_at->timestamp - now()->timestamp,
                        $session->viewer_seen_at->timestamp + config('camera.viewer_lease_seconds') - now()->timestamp)),
                    'ice_servers' => $access->iceServers('agent-'.$session->id)],
                'signals' => $session->signals()->where('sender', 'viewer')->where('id', '>', $after)->orderBy('id')->limit(100)->get(['id', 'payload']),
            ];
        });
    }

    private function withSession(Request $request, string $id, CameraAccess $access, callable $callback)
    {
        return DB::transaction(function () use ($request, $id, $access, $callback) {
            $device = $this->device($request);
            $session = CameraViewSession::whereKey($id)->where('camera_device_id', $device->id)->lockForUpdate()->firstOrFail();
            $access->expire($session, $device);
            if ($session->ended_at) {
                return response()->json(['message' => 'Viewing session ended.'], 409);
            }

            return $callback($session, $device);
        });
    }

    public function answer(Request $request, string $id, CameraAccess $access)
    {
        $data = $request->validate(['answer' => 'required|array:type,sdp', 'answer.type' => 'required|in:answer', 'answer.sdp' => 'required|string|max:100000']);

        return $this->withSession($request, $id, $access, function ($session) use ($data) {
            if ($session->answer && $session->answer !== $data['answer']) {
                return response()->json(['message' => 'Already answered.'], 409);
            }
            $session->update(['answer' => $data['answer'], 'status' => 'active']);

            return ['ok' => true];
        });
    }

    public function signal(Request $request, string $id, CameraAccess $access)
    {
        return $this->withSession($request, $id, $access, function ($session) use ($request, $access) {
            $access->candidates($request, $session, 'agent');

            return ['ok' => true];
        });
    }

    public function end(Request $request, string $id, CameraAccess $access)
    {
        $data = $request->validate(['reason' => 'required|in:disabled_on_tv,camera_busy,camera_missing,network_lost,capture_failed,peer_disconnected,time_limit']);

        return $this->withSession($request, $id, $access, function ($session) use ($data, $access) {
            $access->end($session, $data['reason']);

            return ['ok' => true];
        });
    }
}
