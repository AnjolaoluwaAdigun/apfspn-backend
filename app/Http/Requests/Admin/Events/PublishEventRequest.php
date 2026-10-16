<?php

namespace App\Http\Requests\Admin\Events;

use App\Http\Requests\ApiFormRequest;

class PublishEventRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['status' => ['prohibited'], 'published_at' => ['prohibited']];
    }
}
