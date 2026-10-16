<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecuritySettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'two_factor_enabled' => $this->resource['two_factor_enabled'],
        ];
    }
}
