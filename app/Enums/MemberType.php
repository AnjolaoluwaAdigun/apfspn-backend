<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MemberType: string
{
    use EnumHelpers;

    case Individual = 'individual';
    case Corporate = 'corporate';
    case Institutional = 'institutional';
}
