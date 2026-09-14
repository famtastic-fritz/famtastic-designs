<?php

use App\Http\Controllers\NewsletterController;
use Illuminate\Support\Facades\Route;

Route::post('/api/newsletter/signup', [NewsletterController::class, 'signup'])->middleware('throttle:6,1');
Route::get('/api/newsletter/{action}/{subscriber}/{token}', [NewsletterController::class, 'show'])
    ->whereIn('action', ['confirm', 'unsubscribe'])->middleware('throttle:30,1');
Route::post('/api/newsletter/{action}/{subscriber}/{token}', [NewsletterController::class, 'respond'])
    ->whereIn('action', ['confirm', 'unsubscribe'])->middleware('throttle:10,1');
Route::get('/admin/api/newsletter/subscribers', [NewsletterController::class, 'subscribers'])->middleware(['auth', 'owner', 'throttle:60,1']);
Route::get('/admin/newsletter', [NewsletterController::class, 'ownerPage'])->middleware(['auth', 'owner', 'throttle:60,1']);
