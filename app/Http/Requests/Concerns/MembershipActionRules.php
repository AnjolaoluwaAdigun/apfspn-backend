<?php

namespace App\Http\Requests\Concerns;

trait MembershipActionRules
{
    protected function prohibitedMembershipRules(): array
    {
        return array_fill_keys([
            'uuid', 'user_uuid', 'member_id', 'member_type', 'status', 'membership_status', 'approved_at',
            'approved_by_admin_uuid', 'expires_at', 'rejected_at', 'suspended_at', 'reactivated_at',
        ], ['prohibited']);
    }

    protected function reasonRules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
