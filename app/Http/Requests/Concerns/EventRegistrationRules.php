<?php

namespace App\Http\Requests\Concerns;

trait EventRegistrationRules
{
    protected function prohibitedRegistrationRules(): array
    {
        return array_fill_keys([
            'uuid', 'user_uuid', 'event_uuid', 'status', 'waitlist_position',
            'registered_at', 'promoted_at', 'cancelled_at', 'attended_at',
        ], ['prohibited']);
    }
}
