<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum EventStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function canBePublished(): bool
    {
        return $this === self::Draft;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::Published;
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Published], true);
    }

    public static function publiclyVisible(): array
    {
        return [self::Published, self::Completed];
    }
}
