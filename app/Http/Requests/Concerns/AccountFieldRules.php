<?php

namespace App\Http\Requests\Concerns;

trait AccountFieldRules
{
    private const MEMBER_ACCOUNT_FIELDS = [
        'uuid', 'user_uuid', 'email', 'email_verified_at', 'status', 'two_factor_enabled',
        'member_id', 'membership_status', 'password',
    ];

    private const ADMIN_ACCOUNT_FIELDS = [
        'uuid', 'admin_uuid', 'email', 'status', 'must_reset_password', 'role_uuid', 'roles', 'permissions',
    ];

    protected function prohibitedMemberAccountRules(array $accepted): array
    {
        return array_fill_keys(array_values(array_diff(self::MEMBER_ACCOUNT_FIELDS, $accepted)), ['prohibited']);
    }

    protected function prohibitedAdminAccountRules(): array
    {
        return array_fill_keys(self::ADMIN_ACCOUNT_FIELDS, ['prohibited']);
    }
}
