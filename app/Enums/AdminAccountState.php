<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AdminAccountState: string
{
    use EnumHelpers;

    case Active = 'active';
    case Invited = 'invited';
    case Disabled = 'disabled';
}
