<?php

namespace App\Models;

use App\Enums\MemberActivityType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MemberActivity extends Model
{
    use HasUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_uuid',
        'type',
        'subject_type',
        'subject_uuid',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'type' => MemberActivityType::class,
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo(id: 'subject_uuid');
    }
}
