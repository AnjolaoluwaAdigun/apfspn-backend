<?php

namespace App\Http\Requests\Admin\Events;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AttendeeFilterRules;

class ExportAttendeesRequest extends ApiFormRequest
{
    use AttendeeFilterRules;

    public function rules(): array
    {
        return $this->attendeeFilterRules();
    }
}
