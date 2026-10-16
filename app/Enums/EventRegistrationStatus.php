<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum EventRegistrationStatus: string
{
    use EnumHelpers;

    case Registered = 'registered';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
    case Attended = 'attended';

    public function isActive(): bool
    {
        return $this !== self::Cancelled;
    }

    public function holdsSeat(): bool
    {
        return in_array($this, [self::Registered, self::Attended], true);
    }

    public static function seatHolding(): array
    {
        return [self::Registered, self::Attended];
    }

    public static function active(): array
    {
        return [self::Registered, self::Waitlisted, self::Attended];
    }
}
