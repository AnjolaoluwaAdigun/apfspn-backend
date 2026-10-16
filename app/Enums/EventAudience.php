<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum EventAudience: string
{
    use EnumHelpers;

    case Public = 'public';
    case MembersOnly = 'members_only';
}
