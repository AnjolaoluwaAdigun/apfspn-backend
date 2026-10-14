<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TokenAudience: string
{
    use EnumHelpers;

    case Member = 'member';
    case Admin = 'admin';
}
