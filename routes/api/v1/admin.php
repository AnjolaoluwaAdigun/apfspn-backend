<?php

use App\Enums\ePermission;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\RoleController;
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

Route::get('dashboard', [DashboardController::class, 'show'])
    ->middleware(['auth:sanctum', 'token:admin', $can(ePermission::DashboardView)])
    ->name('dashboard');

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

Route::prefix('articles')->name('articles.')->controller(ArticleController::class)->middleware(['auth:sanctum', 'token:admin'])->group(function () use ($can) {
    Route::get('/', 'index')->middleware($can(ePermission::ArticlesView))->name('index');
    Route::post('/', 'store')->middleware($can(ePermission::ArticlesCreate))->name('store');

    Route::prefix('{article}')->whereUuid('article')->group(function () use ($can) {
        Route::get('/', 'show')->middleware($can(ePermission::ArticlesView))->name('show');
        Route::put('/', 'update')->middleware([$can(ePermission::ArticlesUpdate), 'can:update,article'])->name('update');
        Route::delete('/', 'destroy')->middleware($can(ePermission::ArticlesDelete))->name('destroy');
        Route::post('submit', 'submit')->middleware([$can(ePermission::ArticlesSubmit), 'can:submit,article'])->name('submit');
        Route::post('publish', 'publish')->middleware($can(ePermission::ArticlesPublish))->name('publish');
        Route::post('schedule', 'schedule')->middleware($can(ePermission::ArticlesPublish))->name('schedule');
        Route::post('unschedule', 'unschedule')->middleware($can(ePermission::ArticlesPublish))->name('unschedule');
        Route::post('send-back', 'sendBack')->middleware($can(ePermission::ArticlesPublish))->name('send-back');
        Route::post('archive', 'archive')->middleware($can(ePermission::ArticlesArchive))->name('archive');
        Route::post('unarchive', 'unarchive')->middleware($can(ePermission::ArticlesArchive))->name('unarchive');
        Route::get('revisions', 'revisions')->middleware($can(ePermission::ArticlesView))->name('revisions');
        Route::post('revisions/{revision}/restore', 'restoreRevision')->whereUuid('revision')->middleware([$can(ePermission::ArticlesUpdate), 'can:update,article'])->name('revisions.restore');
    });
});

Route::prefix('users')->name('users.')->controller(AdminUserController::class)->middleware(['auth:sanctum', 'token:admin'])->group(function () use ($can) {
    Route::get('/', 'index')->middleware($can(ePermission::AdminsView))->name('index');
    Route::post('/', 'store')->middleware($can(ePermission::AdminsCreate))->name('store');

    Route::prefix('{admin}')->whereUuid('admin')->group(function () use ($can) {
        Route::get('/', 'show')->middleware($can(ePermission::AdminsView))->name('show');
        Route::put('/', 'update')->middleware($can(ePermission::AdminsUpdate))->name('update');
        Route::delete('/', 'destroy')->middleware($can(ePermission::AdminsDelete))->name('destroy');
        Route::post('resend-invite', 'resendInvite')->middleware([$can(ePermission::AdminsCreate), 'throttle:invite'])->name('resend-invite');
        Route::post('disable', 'disable')->middleware($can(ePermission::AdminsUpdate))->name('disable');
        Route::post('enable', 'enable')->middleware($can(ePermission::AdminsUpdate))->name('enable');
        Route::get('audit-logs', 'auditLogs')->middleware($can(ePermission::AuditLogsView))->name('audit-logs');
    });
});

Route::prefix('roles')->name('roles.')->controller(RoleController::class)->middleware(['auth:sanctum', 'token:admin'])->group(function () use ($can) {
    Route::get('/', 'index')->middleware($can(ePermission::RolesView))->name('index');
    Route::get('permissions', 'permissions')->middleware($can(ePermission::RolesView))->name('permissions');
    Route::post('/', 'store')->middleware($can(ePermission::RolesCreate))->name('store');

    Route::prefix('{role}')->whereUuid('role')->group(function () use ($can) {
        Route::get('/', 'show')->middleware($can(ePermission::RolesView))->name('show');
        Route::put('/', 'update')->middleware($can(ePermission::RolesUpdate))->name('update');
        Route::delete('/', 'destroy')->middleware($can(ePermission::RolesDelete))->name('destroy');
    });
});
