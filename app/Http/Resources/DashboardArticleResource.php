<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->category,
            'author' => $this->author ? ['uuid' => $this->author->uuid, 'name' => $this->author->name] : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
