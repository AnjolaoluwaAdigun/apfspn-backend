<?php

namespace App\Http\Requests\Concerns;

use App\Enums\EventRegistrationStatus;
use Illuminate\Validation\Rule;

trait AttendeeFilterRules
{
    protected function attendeeFilterRules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(EventRegistrationStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
