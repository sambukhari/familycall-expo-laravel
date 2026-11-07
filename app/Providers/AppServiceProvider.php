<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('camera-pair', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('camera-agent', fn (Request $r) => Limit::perMinute(180)->by($r->attributes->get('cameraDevice')?->id ?? $r->ip()));
        foreach (['security' => 5, 'pair-create' => 10, 'start' => 10, 'ice' => 30, 'signals' => 180] as $action => $limit) {
            RateLimiter::for('camera-'.$action, fn (Request $r) => Limit::perMinute($limit)->by($r->user()?->id ?? $r->ip()));
        }
    }
}

