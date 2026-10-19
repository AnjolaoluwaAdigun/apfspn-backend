<?php

namespace App\Providers;

use App\Enums\DashboardSection;
use App\Helpers\Uploads\CloudinaryPrivateFileStore;
use App\Helpers\Uploads\PrivateFileStore;
use App\Models\Admin;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Policies\ArticlePolicy;
use App\Policies\EventPolicy;
use App\Services\Dashboard\DashboardCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public array $bindings = [
        PrivateFileStore::class => CloudinaryPrivateFileStore::class,
    ];

    public function boot(): void
    {
        Blueprint::macro('uuidPrimary', function () {
            /** @var Blueprint $this */
            return $this->uuid('uuid')->primary();
        });

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Article::class, ArticlePolicy::class);

        $this->configureRateLimiting();
        $this->forgetDashboardCacheOnChanges();
    }

    private function forgetDashboardCacheOnChanges(): void
    {
        $sections = [
            User::class => DashboardSection::Members,
            Membership::class => DashboardSection::Members,
            Event::class => DashboardSection::Events,
            EventRegistration::class => DashboardSection::Events,
            Article::class => DashboardSection::Articles,
            AuditLog::class => DashboardSection::RecentActivity,
            Admin::class => DashboardSection::RecentActivity,
        ];

        foreach ($sections as $model => $section) {
            $forget = fn () => DB::afterCommit(fn () => $this->app->make(DashboardCache::class)->forget($section));

            $model::saved($forget);
            $model::deleted($forget);
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(self::emailKey($request)));

        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(5)->by(
            $request->filled('challenge_token') ? self::tokenKey($request, 'challenge_token') : self::emailKey($request),
        ));

        RateLimiter::for('reset', fn (Request $request) => Limit::perMinute(3)->by(match (true) {
            $request->filled('reset_token') => self::tokenKey($request, 'reset_token'),
            $request->filled('password_token') => self::tokenKey($request, 'password_token'),
            default => self::emailKey($request),
        }));

        RateLimiter::for('refresh', fn (Request $request) => Limit::perMinute(10)->by(
            $request->ip().'|'.hash('sha256', (string) $request->bearerToken()),
        ));

        RateLimiter::for('invite', function (Request $request) {
            $admin = $request->route('admin');

            return Limit::perMinute(3)->by($request->ip().'|'.(is_object($admin) ? $admin->getKey() : (string) $admin));
        });

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
