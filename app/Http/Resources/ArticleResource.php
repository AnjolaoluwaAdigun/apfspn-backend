<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->routeIs('v1.admin.*');

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->unless($request->routeIs('v1.articles.index', 'v1.admin.articles.index'), $this->body),
            'cover_image_url' => $this->cover_image_url,
            'category' => $this->category,
            'category_label' => $this->category->label(),
            'tags' => $this->tags ?? [],
            'status' => $this->when($isAdmin, $this->status),
            'status_label' => $this->when($isAdmin, fn () => $this->status->label()),
            'publish_at' => $this->when($isAdmin, fn () => $this->publish_at?->toIso8601String()),
            'published_at' => $this->published_at?->toIso8601String(),
            'archived_at' => $this->when($isAdmin, fn () => $this->archived_at?->toIso8601String()),
            'view_count' => $this->view_count,
            'author' => $this->whenLoaded('author', fn () => $this->author ? [
                'uuid' => $this->when($isAdmin, $this->author->uuid),
                'name' => $this->author->name,
            ] : null),
            'reviewer' => $this->when($isAdmin && $this->relationLoaded('reviewer'), fn () => $this->reviewer ? [
                'uuid' => $this->reviewer->uuid,
                'name' => $this->reviewer->name,
            ] : null),
            'review_note' => $this->when($isAdmin, $this->review_note),
            'revision_count' => $this->when($isAdmin && $this->revisions_count !== null, fn () => $this->revisions_count),
            'author_admin_uuid' => $this->when($isAdmin, $this->author_admin_uuid),
            'created_by_admin_uuid' => $this->when($isAdmin, $this->created_by_admin_uuid),
            'updated_by_admin_uuid' => $this->when($isAdmin, $this->updated_by_admin_uuid),
            'created_at' => $this->when($isAdmin, fn () => $this->created_at?->toIso8601String()),
            'updated_at' => $this->when($isAdmin, fn () => $this->updated_at?->toIso8601String()),
        ];
    }
}
