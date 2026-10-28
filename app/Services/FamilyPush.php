<?php
namespace App\Services;

use App\Models\FamilyDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FamilyPush {
    // Expo Push Service handles routing to FCM. Credentials belong in EAS, not this Laravel app.
    public function incoming(string $to, string $from, int $callId): void {
        $tokens = FamilyDeviceToken::where('device_id', $to)->pluck('token')->filter(
            fn ($token) => preg_match('/^ExponentPushToken\\[[^\\]]+\\]$|^ExpoPushToken\\[[^\\]]+\\]$/', (string) $token)
        )->values();
        if ($tokens->isEmpty()) {
            Log::warning('FamilyCall Expo push skipped: no registered Expo tokens', ['to' => $to, 'call_id' => $callId]);
            return;
        }
        foreach ($tokens->chunk(100) as $chunk) {
            $messages = $chunk->map(fn ($token) => [
                'to' => $token,
                'title' => 'Incoming FamilyCall',
                'body' => 'Call from ' . $from,
                'sound' => 'default',
                'priority' => 'high',
                'ttl' => 60,
                'channelId' => 'familycall-incoming',
                'categoryId' => 'familycall-actions',
                'data' => ['type' => 'incoming_call', 'callId' => (string) $callId, 'from' => $from, 'to' => $to],
            ])->all();
            try {
                $response = Http::acceptJson()->asJson()->timeout(12)->retry(2, 500)->post(
                    'https://exp.host/--/api/v2/push/send', $messages
                );
                if (!$response->successful()) {
                    Log::error('FamilyCall Expo push HTTP failure', ['status' => $response->status(), 'body' => substr($response->body(), 0, 400)]);
                    continue;
                }
                foreach (($response->json('data') ?? []) as $i => $ticket) {
                    if (($ticket['status'] ?? null) !== 'ok') {
                        Log::warning('FamilyCall Expo push ticket error', [
                            'to' => $to, 'call_id' => $callId, 'error' => $ticket['details']['error'] ?? $ticket['message'] ?? 'unknown'
                        ]);
                    } else {
                        Log::info('FamilyCall Expo push ticket accepted', ['to' => $to, 'call_id' => $callId, 'ticket_id' => $ticket['id'] ?? null]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('FamilyCall Expo push exception', ['error' => $e->getMessage()]);
            }
        }
    }
}
