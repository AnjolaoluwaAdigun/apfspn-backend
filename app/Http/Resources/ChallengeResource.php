<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'challenge_token' => $this->resource['challenge_token'],
            'mode' => $this->resource['mode'],
            'expires_in' => $this->resource['expires_in'],
            'retry_after_seconds' => $this->resource['retry_after_seconds'],
        ];
    }
}
