<?php

namespace App\Http\Requests\Member\Registration;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\RegistrationRules;

class RegisterCorporateRequest extends ApiFormRequest
{
    use RegistrationRules;

    public function rules(): array
    {
        return array_merge($this->contactRules(), $this->documentRules('registration_document'), $this->prohibitedRegistrationRules(), [
            'company_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:100'],
            'year_established' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'business_type' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
        ]);
    }
}
