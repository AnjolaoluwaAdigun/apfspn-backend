<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this['status'],
            'app' => $this['app'],
            'version' => $this['version'],
            'time' => $this['time']->toIso8601String(),
        ];
    }
}
