<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PairSecret
{
    public function handle(Request $request, Closure $next)
    {
        $device = $request->header('X-Device-ID', '');
        $provided = $request->header('X-Pair-Secret', '');
        $configured = config('services.family_call_secret');

        abort_unless(in_array($device, ['tv', 'phone'], true), 403, 'Unknown device');
        abort_unless(
            is_string($configured) && $configured !== ''
            && is_string($provided) && $provided !== ''
            && hash_equals($configured, $provided),
            403,
            'Invalid pairing secret'
        );

        return $next($request);
    }
}
