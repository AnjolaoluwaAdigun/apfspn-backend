<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DashboardSection: string
{
    use EnumHelpers;

    case Members = 'members';
    case Events = 'events';
    case Articles = 'articles';
    case RecentActivity = 'recent_activity';

    public function cacheKey(): string
    {
        return 'dashboard:'.$this->value;
    }
}
