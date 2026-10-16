<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Rules\UploadableFile;
use App\Services\Articles\ArticleHtmlSanitizer;
use Illuminate\Validation\Rule;

trait ArticleRules
{
    private const PROHIBITED_ARTICLE_FIELDS = [
        'uuid', 'status', 'cover_image_url', 'publish_at', 'published_at', 'archived_at', 'view_count',
        'author_admin_uuid', 'reviewed_by_admin_uuid', 'review_note',
        'created_by_admin_uuid', 'updated_by_admin_uuid', 'deleted_at',
    ];

    private const COVER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected function articleRules(?string $articleUuid = null): array
    {
        $updating = $articleUuid !== null;
        $required = $updating ? ['sometimes', 'required'] : ['required'];
        $optional = $updating ? ['sometimes', 'nullable'] : ['nullable'];

        return array_merge($this->prohibitedArticleRules(), [
            'title' => [...$required, 'string', 'max:200'],
            'slug' => [...$optional, 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('articles', 'slug')->ignore($articleUuid, 'uuid')],
            'excerpt' => [...$optional, 'string', 'max:500'],
            'body' => [...$required, 'string', 'max:'.ArticleHtmlSanitizer::MAX_LENGTH],
            'cover_image' => [...$optional, new UploadableFile(self::COVER_EXTENSIONS, allowOwnUrl: true)],
            'category' => [...$required, Rule::enum(ArticleCategory::class)],
            'tags' => [...$optional, 'array', 'max:10'],
            'tags.*' => ['required', 'string', 'max:50'],
        ]);
    }

    protected function prohibitedArticleRules(): array
    {
        return array_fill_keys(self::PROHIBITED_ARTICLE_FIELDS, ['prohibited']);
    }

    protected function workflowRules(array $rules = []): array
    {
        return array_merge(array_fill_keys(['status', 'published_at', 'reviewed_by_admin_uuid', 'author_admin_uuid'], ['prohibited']), $rules);
    }

    protected function articleFilterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ArticleStatus::class)],
            'category' => ['nullable', Rule::enum(ArticleCategory::class)],
            'author_uuid' => ['nullable', 'uuid'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
        ];
    }

    protected function publicArticleFilterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(ArticleCategory::class)],
        ];
    }
}
