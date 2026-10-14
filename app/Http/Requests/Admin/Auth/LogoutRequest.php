<?php

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\ApiFormRequest;

class LogoutRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [];
    }
}
