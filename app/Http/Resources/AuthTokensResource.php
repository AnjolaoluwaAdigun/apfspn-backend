<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokensResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource['access_token'],
            'refresh_token' => $this->resource['refresh_token'],
            'token_type' => $this->resource['token_type'],
            'expires_in' => $this->resource['expires_in'],
            'refresh_expires_in' => $this->resource['refresh_expires_in'],
        ];
    }
}
