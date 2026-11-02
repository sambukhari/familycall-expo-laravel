<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\DashboardController;

Route::get('/',fn()=>redirect()->route('family.admin.dashboard'));
Route::middleware('guest')->group(function(){
 Route::get('/admin/login',[SessionController::class,'loginPage'])->name('login');
 Route::post('/admin/login',[SessionController::class,'login'])->middleware('throttle:5,1')->name('family.admin.login.submit');
});
Route::middleware(['auth','family.admin'])->prefix('admin')->name('family.admin.')->group(function(){
 Route::get('/',[DashboardController::class,'index'])->name('dashboard');
 Route::post('/logout',[SessionController::class,'logout'])->name('logout');
 Route::patch('/devices/{device}/authorization',[DashboardController::class,'authorizeDevice'])->name('device.auth');
 Route::patch('/devices/{device}/label',[DashboardController::class,'rename'])->name('device.label');
 Route::post('/devices/{device}/ping',[DashboardController::class,'ping'])->name('device.ping');
 Route::post('/devices/{device}/notification',[DashboardController::class,'notification'])->middleware('throttle:10,1')->name('device.notify');
 Route::post('/messages',[DashboardController::class,'sendMessage'])->middleware('throttle:30,1')->name('messages.send');
});
