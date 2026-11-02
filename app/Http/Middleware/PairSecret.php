<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\FamilyDevice;

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

        $record = FamilyDevice::firstOrCreate(
            ['device_id'=>$device],
            ['label'=>ucfirst($device),'authorized'=>true]
        );
        abort_unless($record->authorized,403,'Device blocked by administrator');
        // Throttle presence writes to protect the DB during WebRTC's frequent signaling polls.
        if (!$record->last_seen_at || $record->last_seen_at->lt(now()->subSeconds(20))) {
            $record->update(['last_seen_at'=>now(),'platform'=>'android']);
        }
        return $next($request);
    }
}
