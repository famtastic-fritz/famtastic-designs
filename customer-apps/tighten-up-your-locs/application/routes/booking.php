<?php

use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

// Loaded from web.php: private mutations and customer response POSTs retain session CSRF.
Route::middleware(['auth', 'owner', 'throttle:120,1'])->prefix('admin/api')->group(function () {
    Route::get('/requests', [BookingController::class, 'requests']);
    Route::get('/appointments', [BookingController::class, 'appointments']);
    Route::post('/appointments', [BookingController::class, 'command']);
    Route::get('/openings', [BookingController::class, 'openings']);
    Route::post('/openings', [BookingController::class, 'openingCommand']);
});

Route::post('/api/booking-request/{siteKey}', [BookingController::class, 'receive'])->middleware('throttle:6,1');
Route::get('/api/booking-availability/{siteKey}', [BookingController::class, 'availability'])->middleware('throttle:60,1');
Route::get('/appointment/{appointment}/{token}', [BookingController::class, 'responsePage'])->middleware('throttle:30,1');
Route::post('/appointment/{appointment}/{token}', [BookingController::class, 'respond'])->middleware('throttle:10,1');
