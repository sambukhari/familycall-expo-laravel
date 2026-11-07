<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\DevicePushController;
use App\Http\Controllers\Api\DeviceControlController;
Route::post('/camera/pair', [\App\Http\Controllers\Api\CameraAgentController::class, 'pair'])->middleware('throttle:camera-pair');
Route::prefix('camera')->middleware([\App\Http\Middleware\AuthenticateCamera::class, 'throttle:camera-agent'])->group(function () {
 Route::post('/poll', [\App\Http\Controllers\Api\CameraAgentController::class, 'poll']);
 Route::post('/sessions/{id}/answer', [\App\Http\Controllers\Api\CameraAgentController::class, 'answer'])->whereUuid('id');
 Route::post('/sessions/{id}/signals', [\App\Http\Controllers\Api\CameraAgentController::class, 'signal'])->whereUuid('id');
 Route::post('/sessions/{id}/end', [\App\Http\Controllers\Api\CameraAgentController::class, 'end'])->whereUuid('id');
});
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

Route::prefix('account')->group(function () {
 Route::post('/register', [\App\Http\Controllers\Api\FamilyAccountController::class, 'register'])->middleware('throttle:5,1');
 Route::post('/login', [\App\Http\Controllers\Api\FamilyAccountController::class, 'login'])->middleware('throttle:5,1');
 Route::middleware(\App\Http\Middleware\AuthenticateFamilyAccount::class)->group(function () {
  Route::get('/me', [\App\Http\Controllers\Api\FamilyAccountController::class, 'me']);
  Route::get('/members', [\App\Http\Controllers\Api\FamilyAccountController::class, 'members']);
  Route::post('/push-token', [\App\Http\Controllers\Api\FamilyCallController::class, 'pushToken']);
  Route::get('/calls/active', [\App\Http\Controllers\Api\FamilyCallController::class, 'active']);
  Route::post('/calls', [\App\Http\Controllers\Api\FamilyCallController::class, 'start']);
  Route::post('/calls/{call}/answer', [\App\Http\Controllers\Api\FamilyCallController::class, 'answer']);
  Route::post('/calls/{call}/end', [\App\Http\Controllers\Api\FamilyCallController::class, 'end']);
  Route::get('/calls/{call}/signals', [\App\Http\Controllers\Api\FamilyCallController::class, 'signals']);
  Route::post('/calls/{call}/signals', [\App\Http\Controllers\Api\FamilyCallController::class, 'signal']);
  Route::get('/members/{member}/messages', [\App\Http\Controllers\Api\FamilyMessageController::class, 'index'])->whereNumber('member');
  Route::post('/members/{member}/messages', [\App\Http\Controllers\Api\FamilyMessageController::class, 'store'])->whereNumber('member')->middleware('throttle:60,1');
  Route::post('/members/{member}/read', [\App\Http\Controllers\Api\FamilyMessageController::class, 'markRead'])->whereNumber('member');
  Route::post('/logout', [\App\Http\Controllers\Api\FamilyAccountController::class, 'logout']);
 });
});

