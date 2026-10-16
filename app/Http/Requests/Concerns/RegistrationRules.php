<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait RegistrationRules
{
    use EmailRules, StateRules, UploadRules;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }

        if (is_string($this->input('state_code'))) {
            $this->merge(['state_code' => Str::upper(trim($this->input('state_code')))]);
        }
    }

    protected function contactRules(): array
    {
        $email = $this->emailRules();
        $email['email'][] = Rule::unique('users', 'email');

        return $email + [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/', Rule::unique('users', 'phone')],
            'consent' => ['required', 'accepted'],
        ] + $this->stateRules();
    }

    protected function prohibitedRegistrationRules(): array
    {
        return array_fill_keys(
            ['uuid', 'user_uuid', 'status', 'membership_status', 'member_id', 'member_type', 'password', 'email_verified_at', 'approved_at', 'document_public_id'],
            ['prohibited'],
        );
    }
}
