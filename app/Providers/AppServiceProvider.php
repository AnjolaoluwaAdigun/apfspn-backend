<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Blueprint::macro('uuidPrimary', function () {
            /** @var Blueprint $this */
            return $this->uuid('uuid')->primary();
        });

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(self::emailKey($request)));

        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(5)->by(
            $request->filled('challenge_token') ? self::tokenKey($request, 'challenge_token') : self::emailKey($request),
        ));

        RateLimiter::for('reset', fn (Request $request) => Limit::perMinute(3)->by(
            $request->filled('token') ? self::tokenKey($request, 'token') : self::emailKey($request),
        ));

        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perMinute(3)->by(self::emailKey($request)),
            Limit::perHour(20)->by('ip:'.$request->ip()),
        ]);
    }

    private static function emailKey(Request $request): string
    {
        return $request->ip().'|'.Str::lower(trim((string) $request->input('email')));
    }

    private static function tokenKey(Request $request, string $field): string
    {
        return $request->ip().'|'.hash('sha256', (string) $request->input($field));
    }
}
