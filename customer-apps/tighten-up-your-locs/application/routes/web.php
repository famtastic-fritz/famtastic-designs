<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
Route::get('/', fn()=>redirect('/admin'));
Route::middleware('guest')->group(function() {
    Route::get('/admin/login',fn()=>view('auth',['mode'=>'login']))->name('login');
    Route::post('/admin/login',[AuthController::class,'login'])->middleware('throttle:10,1');
    Route::get('/admin/forgot-password',fn()=>view('auth',['mode'=>'forgot']))->name('password.request');
    Route::post('/admin/forgot-password',[AuthController::class,'requestReset'])->middleware('throttle:3,10')->name('password.email');
    Route::get('/admin/reset-password/{token}',fn(string $token)=>view('auth',['mode'=>'reset','token'=>$token]))->name('password.reset');
    Route::post('/admin/reset-password',[AuthController::class,'reset'])->middleware('throttle:10,1')->name('password.update');
});
Route::post('/admin/logout',[AuthController::class,'logout'])->middleware('auth');
Route::get('/admin',fn()=>view('admin'))->middleware(['auth','owner'])->name('admin');
if (is_file(__DIR__.'/booking.php')) require __DIR__.'/booking.php';
