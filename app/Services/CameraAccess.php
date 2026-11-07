<?php

namespace App\Services;

use App\Models\CameraDevice;
use App\Models\CameraOwnerKey;
use App\Models\CameraViewSession;
use App\Models\FamilyAdminEvent;
use App\Models\FamilyCall;
use Illuminate\Http\Request;

class CameraAccess
{
    public function unlocked(Request $request): bool
    {
        return $request->session()->get('camera_owner_id') === $request->user()->id
            && $request->session()->get('camera_access_until', 0) > now()->timestamp
            && $request->session()->has('camera_viewer_id')
            && CameraOwnerKey::whereKey($request->session()->get('camera_key_id'))
                ->where('user_id', $request->user()->id)->whereNotNull('confirmed_at')->exists();
    }

    public function viewerHash(Request $request): string
    {
        return hash('sha256', (string) $request->session()->get('camera_viewer_id'));
    }

    public function turnReady(): bool
    {
        return strlen((string) config('camera.turn_secret')) >= 32 && count($this->turnUrls()) > 0;
    }

    private function turnUrls(): array
    {
        return array_values(array_filter(config('camera.turn_urls', []), fn ($url) => preg_match('/^turns?:[^\s@]+$/D', $url)));
    }

    public function iceServers(string $identity): array
    {
        abort_unless($this->turnReady(), 503, 'Configure CAMERA_TURN_URLS and CAMERA_TURN_SECRET before internet viewing.');
        $username = (now()->timestamp + 1200).':camera-'.$identity;

        return [['urls' => $this->turnUrls(), 'username' => $username,
            'credential' => base64_encode(hash_hmac('sha1', $username, (string) config('camera.turn_secret'), true))]];
    }

    public function busy(CameraDevice $device): bool
    {
        if (! $device->family_account_id) {
            return false;
        }
        $id = 'account:'.$device->family_account_id;

        return FamilyCall::whereIn('status', ['ringing', 'connected'])
            ->where(fn ($q) => $q->where('caller_id', $id)->orWhere('callee_id', $id))->exists();
    }

    public function expire(CameraViewSession $session, ?CameraDevice $device = null): void
    {
        if ($session->ended_at) {
            return;
        }
        $device ??= $session->device;
        $reason = match (true) {
            (bool) $device->revoked_at => 'revoked',
            ! $device->armed => 'disabled_on_tv',
            ! $device->last_seen_at || $device->last_seen_at->lt(now()->subSeconds(config('camera.online_seconds'))) => 'device_offline',
            $session->expires_at->lte(now()) => 'time_limit',
            $session->viewer_seen_at->lte(now()->subSeconds(config('camera.viewer_lease_seconds'))) => 'viewer_disconnected',
            $this->busy($device) => 'family_call',
            default => null,
        };
        if ($reason) {
            $this->end($session, $reason);
        }
    }

    public function end(CameraViewSession $session, string $reason): void
    {
        if ($session->ended_at) {
            return;
        }
        $session->update(['status' => 'ended', 'ended_at' => now(), 'end_reason' => $reason, 'offer' => null, 'answer' => null]);
        $session->signals()->delete();
        $this->audit('camera.view.ended', $session->device, ['session_id' => $session->id, 'reason' => $reason]);
    }

    public function candidates(Request $request, CameraViewSession $session, string $sender): void
    {
        abort_if($session->ended_at, 409, 'Viewing session has ended.');
        $data = $request->validate([
            'candidates' => 'required|array|min:1|max:20', 'candidates.*.nonce' => 'required|uuid',
            'candidates.*.payload' => 'required|array:candidate,sdpMid,sdpMLineIndex,usernameFragment',
            'candidates.*.payload.candidate' => 'required|string|max:8192',
            'candidates.*.payload.sdpMid' => 'nullable|string|max:64',
            'candidates.*.payload.sdpMLineIndex' => 'nullable|integer|min:0|max:32',
            'candidates.*.payload.usernameFragment' => 'nullable|string|max:256',
        ]);
        abort_if($session->signals()->count() + count($data['candidates']) > 512, 429, 'Candidate limit reached.');
        foreach ($data['candidates'] as $candidate) {
            $session->signals()->firstOrCreate(['sender' => $sender, 'nonce' => $candidate['nonce']], ['payload' => $candidate['payload']]);
        }
    }

    public function audit(string $action, CameraDevice $device, array $details = []): void
    {
        FamilyAdminEvent::create(['actor' => 'camera-owner:'.$device->owner_id, 'action' => $action,
            'device_id' => 'camera:'.$device->id, 'details' => $details]);
    }
}
