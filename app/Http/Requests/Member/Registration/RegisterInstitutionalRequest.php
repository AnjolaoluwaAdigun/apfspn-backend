<?php

namespace App\Http\Requests\Member\Registration;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\RegistrationRules;

class RegisterInstitutionalRequest extends ApiFormRequest
{
    use RegistrationRules;

    public function rules(): array
    {
        return array_merge($this->contactRules(), $this->documentRules('letter_of_authorisation'), $this->prohibitedRegistrationRules(), [
            'institution_name' => ['required', 'string', 'max:255'],
            'institution_type' => ['required', 'string', 'max:100'],
            'registration_number' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
        ]);
    }
}
