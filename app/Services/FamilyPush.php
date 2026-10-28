<?php
namespace App\Services;
use App\Models\FamilyDeviceToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FamilyPush {
    // FCM HTTP v1 using a server-side Firebase service account. Never embed credentials in the app.
    public function incoming(string $to, string $from, int $callId): void {
        $project = (string)config('services.family_firebase_project_id');
        $credentialFile = (string)config('services.family_firebase_credentials');
        if (!$project || !$credentialFile || !is_readable($credentialFile)) {
            Log::warning('FamilyCall FCM not configured');
            return;
        }
        try {
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                json_decode(file_get_contents($credentialFile), true, 512, JSON_THROW_ON_ERROR)
            );
            $auth = $credentials->fetchAuthToken();
            $accessToken = $auth['access_token'] ?? null;
            if (!$accessToken) throw new \RuntimeException('Unable to fetch FCM OAuth token');
            foreach (FamilyDeviceToken::where('device_id', $to)->pluck('token') as $token) {
                $response = Http::withToken($accessToken)->timeout(8)->post(
                    'https://fcm.googleapis.com/v1/projects/'.rawurlencode($project).'/messages:send',
                    ['message'=>[
                        'token'=>$token,
                        'data'=>['type'=>'incoming_call','callId'=>(string)$callId,'from'=>$from,'to'=>$to],
                        'android'=>['priority'=>'HIGH','ttl'=>'60s'],
                    ]]
                );
                if (!$response->successful()) Log::warning('FamilyCall FCM delivery failed', ['status'=>$response->status(), 'body'=>substr($response->body(),0,250)]);
            }
        } catch (\Throwable $e) {
            Log::warning('FamilyCall FCM exception', ['message'=>$e->getMessage()]);
        }
    }
}
