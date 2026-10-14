<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = $this->resource;

        return [
            'next_step' => $result['next_step'],
            'tokens' => $this->when(isset($result['tokens']), fn () => new AuthTokensResource($result['tokens'])),
            'admin' => $this->when(isset($result['admin']), fn () => new AdminResource($result['admin'])),
            'member' => $this->when(isset($result['member']), fn () => new MemberResource($result['member'])),
            'challenge' => $this->when(isset($result['challenge']), fn () => new ChallengeResource($result['challenge'])),
            'reset_token' => $this->when(isset($result['reset_token']), fn () => $result['reset_token']),
        ];
    }
}
