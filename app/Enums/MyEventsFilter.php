<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MyEventsFilter: string
{
    use EnumHelpers;

    case Upcoming = 'upcoming';
    case Past = 'past';
    case Attended = 'attended';
    case Waitlisted = 'waitlisted';
}
