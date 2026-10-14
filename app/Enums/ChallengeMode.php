<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ChallengeMode: string
{
    use EnumHelpers;

    case SendNew = 'send_new';
    case Reuse = 'reuse';
    case Blocked = 'blocked';
}
