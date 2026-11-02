<?php
namespace App\Services;

use App\Models\FamilyDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FamilyPush {
    public function test(string $to,string $title,string $body):array {
        $tokens=FamilyDeviceToken::where('device_id',$to)->pluck('token')->filter()->values();
        if ($tokens->isEmpty()) return ['ok'=>false,'message'=>'No Expo token registered for this device'];
        $sent=0;$errors=0;
        foreach($tokens->chunk(100) as $chunk){
            $payload=$chunk->map(fn($token)=>[
                'to'=>$token,'title'=>$title,'body'=>$body,'sound'=>'default','priority'=>'high',
                'ttl'=>120,'channelId'=>'familycall-incoming','data'=>['type'=>'admin_test']
            ])->all();
            try {
                $response=Http::acceptJson()->asJson()->timeout(12)->post('https://exp.host/--/api/v2/push/send',$payload);
                if(!$response->successful()){$errors+=count($payload);continue;}
                foreach(($response->json('data')??[]) as $ticket) {
                    if(($ticket['status']??'')==='ok')$sent++;else $errors++;
                }
            } catch (\\Throwable $e) {Log::warning('Admin Expo test notification failed',['device'=>$to,'reason'=>$e->getMessage()]);$errors+=count($payload);}
        }
        return ['ok'=>$sent>0,'message'=>"Expo accepted {$sent} notification ticket(s); {$errors} failed. Ticket acceptance does not confirm device delivery."];
    }

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
