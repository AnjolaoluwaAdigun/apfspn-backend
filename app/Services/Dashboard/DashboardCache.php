<?php

namespace App\Services\Dashboard;

use App\Enums\DashboardSection;
use Closure;
use Illuminate\Support\Facades\Cache;

class DashboardCache
{
    public function remember(DashboardSection $section, Closure $build): mixed
    {
        return Cache::remember($section->cacheKey(), (int) config('apfspn.dashboard.cache_ttl_seconds'), $build);
    }

    public function forget(DashboardSection ...$sections): void
    {
        foreach ($sections as $section) {
            Cache::forget($section->cacheKey());
        }
    }
}
