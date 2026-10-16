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
}
