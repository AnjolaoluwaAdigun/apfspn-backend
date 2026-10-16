<?php

namespace App\Http\Requests\Admin\Events;

use App\Http\Requests\ApiFormRequest;

class CancelEventRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'status' => ['prohibited'],
            'cancelled_at' => ['prohibited'],
        ];
    }
}
