<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum EventNotice: string
{
    use EnumHelpers;

    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Promoted = 'promoted';
    case Cancelled = 'cancelled';

    public function subject(string $eventTitle): string
    {
        return match ($this) {
            self::Confirmed => 'You are registered: '.$eventTitle,
            self::Waitlisted => 'You are on the waitlist: '.$eventTitle,
            self::Promoted => 'A seat opened up: '.$eventTitle,
            self::Cancelled => 'Event cancelled: '.$eventTitle,
        };
    }

    public function view(): string
    {
        return 'mail.event-'.$this->value;
    }
}
