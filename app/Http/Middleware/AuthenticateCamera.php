<?php

namespace App\Http\Middleware;

use App\Models\CameraDevice;
use Closure;
use Illuminate\Http\Request;

class AuthenticateCamera
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        abort_unless(is_string($token) && strlen($token) === 64, 401);
        $device = CameraDevice::where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->first();
        abort_unless($device && $device->paired_at, 401, 'Camera pairing is no longer valid.');
        $request->attributes->set('cameraDevice', $device);

        return $next($request)->header('Cache-Control', 'no-store');
    }
}
