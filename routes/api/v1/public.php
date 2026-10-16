<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\PublicArticleController;
use App\Http\Controllers\PublicEventController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->name('health');

Route::prefix('events')->name('events.')->controller(PublicEventController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{slug}', 'show')->where('slug', '[a-z0-9-]+')->name('show');
});

Route::prefix('articles')->name('articles.')->controller(PublicArticleController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{slug}', 'show')->where('slug', '[a-z0-9-]+')->name('show');
});
