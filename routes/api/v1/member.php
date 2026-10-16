<?php

use App\Http\Controllers\Member\AuthController;
use App\Http\Controllers\Member\EventController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\RegistrationController;
use App\Http\Controllers\Member\SecurityController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->middleware('throttle:login')->name('login');
    Route::post('verify-login-otp', 'verifyLoginOtp')->middleware('throttle:otp')->name('verify-login-otp');
    Route::post('resend-otp', 'resendOtp')->middleware('throttle:otp')->name('resend-otp');
    Route::post('forgot-password', 'forgotPassword')->middleware('throttle:reset')->name('forgot-password');
    Route::post('verify-otp', 'verifyOtp')->middleware('throttle:otp')->name('verify-otp');
    Route::post('reset-password', 'resetPassword')->middleware('throttle:reset')->name('reset-password');

    Route::post('refresh', 'refresh')->middleware(['throttle:refresh', 'auth:sanctum', 'token:member,refresh'])->name('refresh');

    Route::middleware(['auth:sanctum', 'token:member'])->group(function () {
        Route::post('logout', 'logout')->name('logout');
        Route::post('change-password', 'changePassword')->name('change-password');
        Route::get('me', 'me')->name('me');
    });
});

Route::prefix('register')->name('register.')->controller(RegistrationController::class)->group(function () {
    Route::post('individual', 'individual')->middleware('throttle:registration')->name('individual');
    Route::post('corporate', 'corporate')->middleware('throttle:registration')->name('corporate');
    Route::post('institutional', 'institutional')->middleware('throttle:registration')->name('institutional');
    Route::post('verify-email', 'verifyEmail')->middleware('throttle:otp')->name('verify-email');
    Route::post('resend-verification', 'resendVerification')->middleware('throttle:otp')->name('resend-verification');
    Route::post('create-password', 'createPassword')->middleware('throttle:reset')->name('create-password');
});

Route::middleware(['auth:sanctum', 'token:member'])->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('security', [SecurityController::class, 'show'])->name('security.show');
    Route::put('security', [SecurityController::class, 'update'])->name('security.update');
});

Route::prefix('events')->name('events.')->controller(EventController::class)->middleware(['auth:sanctum', 'token:member'])->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('{event}/register', 'register')->whereUuid('event')->name('register');
    Route::delete('{event}/register', 'cancel')->whereUuid('event')->name('cancel');
});
