<?php

namespace App\Http\Requests\Admin\Users;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AdminUserRules;
use App\Http\Requests\Concerns\EmailRules;
use Illuminate\Support\Str;

class InviteAdminRequest extends ApiFormRequest
{
    use AdminUserRules, EmailRules;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return array_merge(
            $this->prohibitedAdminRules(),
            $this->emailRules(),
            $this->roleUuidRules(['required']),
            ['name' => ['required', 'string', 'max:200']],
        );
    }
}
