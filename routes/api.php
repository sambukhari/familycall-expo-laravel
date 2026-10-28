<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\DevicePushController;
Route::middleware('pair.secret')->group(function () {
 Route::post('/devices/push-token', [DevicePushController::class, 'store']);
 Route::get('/calls/active', [CallController::class, 'active']);
 Route::post('/calls', [CallController::class, 'start']);
 Route::post('/calls/{call}/answer', [CallController::class, 'answer']);
 Route::post('/calls/{call}/end', [CallController::class, 'end']);
 Route::get('/calls/{call}/signals', [CallController::class, 'signals']);
 Route::post('/calls/{call}/signals', [CallController::class, 'signal']);
});
