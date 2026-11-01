<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyDeviceToken;
use Illuminate\Http\Request;

class DevicePushController extends Controller {
    public function store(Request $request) {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512', 'regex:/^(ExponentPushToken|ExpoPushToken)\\[[^\\]]+\\]$/'],
            'platform' => ['required', 'in:android'],
        ]);

        $device = (string) $request->header('X-Device-ID');
        $stored = FamilyDeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['device_id' => $device, 'platform' => $data['platform']]
        );

        // Explicitly verify database persistence; never echo the push token.
        $registered = FamilyDeviceToken::whereKey($stored->getKey())
            ->where('device_id', $device)
            ->exists();

        return response()->json([
            'ok' => $registered,
            'provider' => 'expo',
            'registered' => $registered,
            'device_id' => $device,
            'registered_count' => FamilyDeviceToken::where('device_id', $device)->count(),
        ], $registered ? 200 : 500);
    }

    public function status(Request $request) {
        $device = (string) $request->header('X-Device-ID');
        return response()->json([
            'ok' => true,
            'provider' => 'expo',
            'device_id' => $device,
            'registered_count' => FamilyDeviceToken::where('device_id', $device)->count(),
        ]);
    }
}
