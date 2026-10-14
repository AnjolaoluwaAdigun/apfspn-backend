<?php

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\ApiFormRequest;

class RefreshTokenRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [];
    }
}
