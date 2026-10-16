<?php

namespace App\Http\Requests\Concerns;

use App\Enums\MembershipStatus;
use App\Enums\MemberType;
use Illuminate\Validation\Rule;

trait MemberFilterRules
{
    use PaginationRules;

    protected function filterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'member_type' => ['nullable', Rule::enum(MemberType::class)],
            'status' => ['nullable', Rule::enum(MembershipStatus::class)],
            'joined_from' => ['nullable', 'date_format:Y-m-d'],
            'joined_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joined_from'],
        ];
    }
}
