<?php

namespace App\Models;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'cover_image_url',
        'category',
        'tags',
        'status',
        'publish_at',
        'published_at',
        'archived_at',
        'author_admin_uuid',
        'reviewed_by_admin_uuid',
        'review_note',
        'created_by_admin_uuid',
        'updated_by_admin_uuid',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'category' => ArticleCategory::class,
            'status' => ArticleStatus::class,
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_admin_uuid');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_uuid');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class, 'article_uuid');
    }

    public function isAuthoredBy(Admin $admin): bool
    {
        return $this->author_admin_uuid === $admin->getKey();
    }
}
