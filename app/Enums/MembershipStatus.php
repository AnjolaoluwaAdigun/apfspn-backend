<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MembershipStatus: string
{
    use EnumHelpers;

    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Expired = 'expired';

    public function canBeApproved(): bool
    {
        return $this === self::PendingApproval;
    }

    public function canBeRejected(): bool
    {
        return $this === self::PendingApproval;
    }

    public function canBeSuspended(): bool
    {
        return $this === self::Active;
    }

    public function canBeReactivated(): bool
    {
        return $this === self::Suspended;
    }
}
