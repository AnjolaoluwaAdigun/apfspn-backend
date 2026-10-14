<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ChallengePurpose: string
{
    use EnumHelpers;

    case MemberLogin = 'member_login';
    case AdminLogin = 'admin_login';
    case PasswordReset = 'password_reset';
}
