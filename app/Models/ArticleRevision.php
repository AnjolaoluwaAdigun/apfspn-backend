<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ArticleRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleRevision extends Model
{
    /** @use HasFactory<ArticleRevisionFactory> */
    use HasFactory, HasUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'article_uuid',
        'title',
        'excerpt',
        'body',
        'edited_by_admin_uuid',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_uuid');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'edited_by_admin_uuid');
    }
}
