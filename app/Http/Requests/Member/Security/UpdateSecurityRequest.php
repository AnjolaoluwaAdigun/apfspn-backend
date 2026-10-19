<?php

namespace App\Http\Requests\Member\Security;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AccountFieldRules;
use Illuminate\Validation\Rule;

class UpdateSecurityRequest extends ApiFormRequest
{
    use AccountFieldRules;

    public function rules(): array
    {
        return [
            'two_factor_enabled' => ['required', 'boolean'],
            'current_password' => [
                Rule::requiredIf(fn () => $this->has('two_factor_enabled') && ! $this->boolean('two_factor_enabled')),
                'nullable',
                'string',
                'max:255',
            ],
        ] + $this->prohibitedMemberAccountRules(['two_factor_enabled']);
    }
}
