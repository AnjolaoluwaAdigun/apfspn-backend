<?php

namespace App\Http\Requests\Admin\Events;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\EventRules;

class StoreEventRequest extends ApiFormRequest
{
    use EventRules;

    public function rules(): array
    {
        return $this->eventRules();
    }
}
