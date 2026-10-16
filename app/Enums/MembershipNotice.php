<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MembershipNotice: string
{
    use EnumHelpers;

    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Reactivated = 'reactivated';

    public function subject(): string
    {
        return match ($this) {
            self::Approved => 'Your APFSPN membership has been approved',
            self::Rejected => 'An update on your APFSPN membership application',
            self::Suspended => 'Your APFSPN membership has been suspended',
            self::Reactivated => 'Your APFSPN membership has been reactivated',
        };
    }

    public function view(): string
    {
        return 'mail.membership-'.$this->value;
    }
}
