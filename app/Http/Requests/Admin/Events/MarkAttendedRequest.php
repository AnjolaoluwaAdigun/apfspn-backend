<?php

namespace App\Http\Requests\Admin\Events;

use App\Http\Requests\ApiFormRequest;

class MarkAttendedRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['status' => ['prohibited'], 'attended_at' => ['prohibited']];
    }
}
