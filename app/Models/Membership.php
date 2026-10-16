<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\MemberType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'user_uuid',
        'member_type',
        'state_code',
        'member_id',
        'status',
        'approved_at',
        'approved_by_admin_uuid',
        'rejection_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'member_type' => MemberType::class,
            'status' => MembershipStatus::class,
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }
}
