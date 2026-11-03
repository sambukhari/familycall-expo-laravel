<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\DashboardController;

Route::get('/',fn()=>view('welcome'));
Route::middleware('guest')->group(function(){
 Route::get('/admin/login',[SessionController::class,'loginPage'])->name('login');
 Route::post('/admin/login',[SessionController::class,'login'])->middleware('throttle:5,1')->name('family.admin.login.submit');
});
Route::middleware(['auth','family.admin'])->prefix('admin')->name('family.admin.')->group(function(){
 Route::get('/',[DashboardController::class,'index'])->name('dashboard');
 Route::post('/families',[\App\Http\Controllers\Admin\FamilyManagementController::class,'create'])->name('families.create');
 Route::patch('/accounts/{account}/family',[\App\Http\Controllers\Admin\FamilyManagementController::class,'assign'])->name('accounts.family');
 Route::post('/browser-calls',[\App\Http\Controllers\Admin\BrowserCallController::class,'start'])->middleware('throttle:10,1');
 Route::get('/browser-calls/{call}',[\App\Http\Controllers\Admin\BrowserCallController::class,'show']);
 Route::post('/browser-calls/{call}/signals',[\App\Http\Controllers\Admin\BrowserCallController::class,'signal']);
 Route::post('/browser-calls/{call}/end',[\App\Http\Controllers\Admin\BrowserCallController::class,'end']);
 Route::post('/logout',[SessionController::class,'logout'])->name('logout');
 Route::patch('/devices/{device}/authorization',[DashboardController::class,'authorizeDevice'])->name('device.auth');
 Route::patch('/devices/{device}/label',[DashboardController::class,'rename'])->name('device.label');
 Route::post('/devices/{device}/ping',[DashboardController::class,'ping'])->name('device.ping');
 Route::post('/devices/{device}/notification',[DashboardController::class,'notification'])->middleware('throttle:10,1')->name('device.notify');
 Route::post('/messages',[DashboardController::class,'sendMessage'])->middleware('throttle:30,1')->name('messages.send');
});
