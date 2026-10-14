<?php

namespace App\Models;

use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuidPrimaryKey, Notifiable;

    protected $rememberTokenName = false;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'member_type',
        'two_factor_enabled',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'member_type' => MemberType::class,
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
