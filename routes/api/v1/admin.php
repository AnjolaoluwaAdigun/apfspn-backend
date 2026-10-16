<?php

use App\Enums\ePermission;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\MemberController;
use Illuminate\Support\Facades\Route;

$can = fn (ePermission $permission) => 'permission:'.$permission->value;

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

Route::prefix('members')->name('members.')->controller(MemberController::class)->middleware(['auth:sanctum', 'token:admin'])->group(function () use ($can) {
    Route::get('overview', 'overview')->middleware($can(ePermission::MembersView))->name('overview');
    Route::get('pending', 'pending')->middleware($can(ePermission::MembersView))->name('pending');
    Route::get('export', 'export')->middleware($can(ePermission::MembersExport))->name('export');
    Route::get('/', 'index')->middleware($can(ePermission::MembersView))->name('index');

    Route::prefix('{member}')->whereUuid('member')->group(function () use ($can) {
        Route::get('/', 'show')->middleware($can(ePermission::MembersView))->name('show');
        Route::put('/', 'update')->middleware($can(ePermission::MembersUpdate))->name('update');
        Route::delete('/', 'destroy')->middleware($can(ePermission::MembersDelete))->name('destroy');
        Route::post('approve', 'approve')->middleware($can(ePermission::MembersApprove))->name('approve');
        Route::post('reject', 'reject')->middleware($can(ePermission::MembersApprove))->name('reject');
        Route::post('suspend', 'suspend')->middleware($can(ePermission::MembersSuspend))->name('suspend');
        Route::post('reactivate', 'reactivate')->middleware($can(ePermission::MembersSuspend))->name('reactivate');
    });
});

Route::prefix('events')->name('events.')->controller(EventController::class)->middleware(['auth:sanctum', 'token:admin'])->group(function () use ($can) {
    Route::get('/', 'index')->middleware($can(ePermission::EventsView))->name('index');
    Route::post('/', 'store')->middleware($can(ePermission::EventsCreate))->name('store');

    Route::prefix('{event}')->whereUuid('event')->group(function () use ($can) {
        Route::get('/', 'show')->middleware($can(ePermission::EventsView))->name('show');
        Route::put('/', 'update')->middleware([$can(ePermission::EventsUpdate), 'can:update,event'])->name('update');
        Route::delete('/', 'destroy')->middleware($can(ePermission::EventsDelete))->name('destroy');
        Route::post('publish', 'publish')->middleware($can(ePermission::EventsPublish))->name('publish');
        Route::post('cancel', 'cancel')->middleware($can(ePermission::EventsCancel))->name('cancel');
        Route::get('attendees', 'attendees')->middleware($can(ePermission::EventRegistrationsView))->name('attendees');
        Route::get('attendees/export', 'export')->middleware($can(ePermission::EventRegistrationsExport))->name('attendees.export');
        Route::post('attendees/{registration}/attend', 'markAttended')->whereUuid('registration')->middleware($can(ePermission::EventRegistrationsUpdate))->name('attendees.attend');
        Route::get('waitlist', 'waitlist')->middleware($can(ePermission::EventRegistrationsView))->name('waitlist');
    });
});
