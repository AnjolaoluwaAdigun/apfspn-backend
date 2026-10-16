<?php

namespace App\Http\Requests\Member\Registration;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\RegistrationRules;

class RegisterIndividualRequest extends ApiFormRequest
{
    use RegistrationRules;

    public function rules(): array
    {
        return array_merge($this->contactRules(), $this->photoRules(), $this->prohibitedRegistrationRules(), [
            'city' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
