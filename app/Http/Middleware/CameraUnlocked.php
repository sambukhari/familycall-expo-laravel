<?php

namespace App\Http\Middleware;

use App\Services\CameraAccess;
use Closure;
use Illuminate\Http\Request;

class CameraUnlocked
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app(CameraAccess::class)->unlocked($request), 423, 'Unlock camera access with your password and authenticator code.');

        return $next($request)->header('Cache-Control', 'no-store');
    }
}
