<?php

use App\Http\Controllers\Admin\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->middleware('throttle:login')->name('login');
    Route::post('verify-otp', 'verifyOtp')->middleware('throttle:otp')->name('verify-otp');
    Route::post('resend-otp', 'resendOtp')->middleware('throttle:otp')->name('resend-otp');
    Route::post('forgot-password', 'forgotPassword')->middleware('throttle:reset')->name('forgot-password');
    Route::post('reset-password', 'resetPassword')->middleware('throttle:reset')->name('reset-password');

    Route::post('refresh', 'refresh')->middleware(['throttle:refresh', 'auth:sanctum', 'token:admin,refresh'])->name('refresh');

    Route::middleware(['auth:sanctum', 'token:admin'])->group(function () {
        Route::post('logout', 'logout')->name('logout');
        Route::get('me', 'me')->name('me');
    });
});
