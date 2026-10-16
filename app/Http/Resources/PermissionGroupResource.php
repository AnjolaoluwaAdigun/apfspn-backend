<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'module' => $this->resource['module'],
            'label' => $this->resource['label'],
            'permissions' => $this->resource['permissions'],
        ];
    }
}
