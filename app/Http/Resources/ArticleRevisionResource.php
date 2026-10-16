<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'article_uuid' => $this->article_uuid,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'edited_by' => $this->whenLoaded('editor', fn () => $this->editor ? [
                'uuid' => $this->editor->uuid,
                'name' => $this->editor->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
