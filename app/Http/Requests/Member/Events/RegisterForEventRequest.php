<?php

namespace App\Http\Requests\Member\Events;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\EventRegistrationRules;

class RegisterForEventRequest extends ApiFormRequest
{
    use EventRegistrationRules;

    public function rules(): array
    {
        return $this->prohibitedRegistrationRules();
    }
}
