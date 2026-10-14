<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AuthClient: string
{
    use EnumHelpers;

    case Web = 'web';
    case Mobile = 'mobile';
}
