<?php

namespace App\Console\Commands;

use App\Models\CameraDevice;
use App\Models\CameraViewSession;
use App\Services\CameraAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneCameraSessions extends Command
{
    protected $signature = 'camera:prune';

    protected $description = 'Expire abandoned camera sessions and remove old signaling data';

    public function handle(CameraAccess $access): int
    {
        CameraDevice::whereHas('sessions', fn ($q) => $q->whereNull('ended_at'))
            ->eachById(function ($device) use ($access) {
                DB::transaction(function () use ($device, $access) {
                    $device = CameraDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();
                    foreach ($device->sessions()->whereNull('ended_at')->lockForUpdate()->get() as $session) {
                        $access->expire($session, $device);
                    }
                });
            });
        CameraViewSession::where('ended_at', '<', now()->subDays(7))->delete();
        CameraDevice::where('pair_expires_at', '<', now())->update(['pair_code_hash' => null]);
        $this->info('Expired camera sessions and removed old signaling data.');

        return self::SUCCESS;
    }
}
