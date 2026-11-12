<?php

namespace App\Console\Commands;

use App\Models\CameraDevice;
use App\Models\CameraOwnerKey;
use App\Models\User;
use App\Services\CameraAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetCameraAuthenticator extends Command
{
    protected $signature = 'camera:reset-authenticator {email} {--force}';

    protected $description = 'SSH recovery: invalidate camera unlocks and require authenticator enrollment again';

    public function handle(CameraAccess $access): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('End all camera viewing and reset this user’s authenticator?')) {
            return self::FAILURE;
        }
        DB::transaction(function () use ($user, $access) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            CameraOwnerKey::where('user_id', $user->id)->delete();
            foreach (CameraDevice::where('owner_id', $user->id)->lockForUpdate()->get() as $device) {
                foreach ($device->sessions()->whereNull('ended_at')->lockForUpdate()->get() as $session) {
                    $access->end($session, 'authenticator_reset');
                }
                $access->audit('camera.authenticator.reset', $device);
            }
        });
        $this->info('Camera access locked. Unlock again in admin; enroll an authenticator if it is required.');

        return self::SUCCESS;
    }
}
