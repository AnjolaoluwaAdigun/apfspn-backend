<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum EventTimeFilter: string
{
    use EnumHelpers;

    case Upcoming = 'upcoming';
    case Past = 'past';
}
