<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UserStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Disabled = 'disabled';
}
