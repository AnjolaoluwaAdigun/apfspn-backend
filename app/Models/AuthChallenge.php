<?php

namespace App\Models;

use App\Enums\ChallengePurpose;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuthChallenge extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'subject_type',
        'subject_uuid',
        'email_hash',
        'purpose',
        'code_hash',
        'version',
        'attempts',
        'send_count',
        'last_sent_at',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => ChallengePurpose::class,
            'version' => 'integer',
            'attempts' => 'integer',
            'send_count' => 'integer',
            'last_sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo(type: 'subject_type', id: 'subject_uuid');
    }
}
