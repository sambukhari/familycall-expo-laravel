<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\DevicePushController;
use App\Http\Controllers\Api\DeviceControlController;
Route::middleware('pair.secret')->group(function () {
 Route::post('/devices/heartbeat',[DeviceControlController::class,'heartbeat']);
 Route::get('/devices/commands',[DeviceControlController::class,'commands']);
 Route::post('/devices/commands/{command}/ack',[DeviceControlController::class,'acknowledge']);
 Route::get('/messages',[DeviceControlController::class,'messages']);
 Route::post('/messages',[DeviceControlController::class,'sendMessage']);
 Route::post('/messages/{message}/read',[DeviceControlController::class,'markRead']);
 Route::post('/devices/push-token', [DevicePushController::class, 'store']);
 Route::get('/devices/push-status', [DevicePushController::class, 'status']);
 Route::get('/calls/active', [CallController::class, 'active']);
 Route::post('/calls', [CallController::class, 'start']);
 Route::post('/calls/{call}/answer', [CallController::class, 'answer']);
 Route::post('/calls/{call}/end', [CallController::class, 'end']);
 Route::get('/calls/{call}/signals', [CallController::class, 'signals']);
 Route::post('/calls/{call}/signals', [CallController::class, 'signal']);
});
