<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CameraDevice;
use App\Models\CameraOwnerKey;
use App\Services\CameraAccess;
use App\Services\CameraTotp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CameraSecurityController extends Controller
{
    public function lock(Request $request, CameraAccess $access)
    {
        $viewerHash = $access->viewerHash($request);
        DB::transaction(function () use ($request, $access, $viewerHash) {
            foreach (CameraDevice::where('owner_id', $request->user()->id)->lockForUpdate()->get() as $device) {
                foreach ($device->sessions()->where('viewer_hash', $viewerHash)->whereNull('ended_at')->lockForUpdate()->get() as $session) {
                    $access->end($session, 'owner_locked');
                }
            }
        });
        $request->session()->forget(['camera_owner_id', 'camera_key_id', 'camera_access_until', 'camera_viewer_id']);

        return response()->json(['unlocked' => false])->header('Cache-Control', 'no-store');
    }

    private function password(Request $request): void
    {
        $request->validate(['password' => 'required|string|max:1024']);
        abort_unless(Hash::check($request->string('password')->toString(), $request->user()->password), 422, 'Password or authenticator code is incorrect.');
    }

    public function enroll(Request $request, CameraTotp $totp)
    {
        $this->password($request);

        return DB::transaction(function () use ($request, $totp) {
            // Serialize enrollment, including the first row creation.
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $key = CameraOwnerKey::firstOrNew(['user_id' => $request->user()->id]);
            abort_if($key->confirmed_at, 409, 'An authenticator is already configured.');
            $secret = $totp->generateSecret();
            $key->fill(['secret' => $secret, 'last_counter' => null])->save();
            $request->session()->put('camera_enrollment_id', $key->id);

            return response()->json(['secret' => $secret, 'issuer' => 'FamilyCall Camera',
                'account' => $request->user()->email])->header('Cache-Control', 'no-store');
        });
    }

    public function unlock(Request $request, CameraTotp $totp)
    {
        $this->password($request);
        $data = $request->validate(['code' => ['required', 'regex:/^[0-9]{6}$/D']]);

        return DB::transaction(function () use ($request, $totp, $data) {
            $key = CameraOwnerKey::where('user_id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($key, 422, 'Set up your authenticator first.');
            if (! $key->confirmed_at) {
                abort_unless($request->session()->get('camera_enrollment_id') === $key->id
                    && $key->updated_at->gt(now()->subMinutes(10)), 422, 'Authenticator setup expired. Start again.');
            }
            $counter = $totp->verify($key->secret, $data['code'], $key->last_counter);
            abort_if($counter === null, 422, 'Password or authenticator code is incorrect. Wait for a new code if this one was already used.');
            $key->update(['confirmed_at' => $key->confirmed_at ?? now(), 'last_counter' => $counter]);
            $request->session()->regenerate();
            $request->session()->forget('camera_enrollment_id');
            $request->session()->put([
                'camera_owner_id' => $request->user()->id,
                'camera_key_id' => $key->id,
                'camera_access_until' => now()->timestamp + config('camera.unlock_seconds'),
                'camera_viewer_id' => Str::random(64),
            ]);

            return response()->json(['unlocked' => true, 'csrf_token' => csrf_token()])->header('Cache-Control', 'no-store');
        });
    }
}
