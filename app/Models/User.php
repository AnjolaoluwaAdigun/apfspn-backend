<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuidPrimaryKey, Notifiable, SoftDeletes;

    protected $rememberTokenName = false;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'phone',
        'password',
        'status',
        'bio',
        'photo_url',
        'state_code',
        'city',
        'directory_visible',
        'two_factor_enabled',
        'consent_accepted_at',
        'last_login_at',
        'deleted_email_hash',
    ];

    protected $hidden = [
        'password',
        'deleted_email_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'consent_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'directory_visible' => 'boolean',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class, 'user_uuid');
    }

    public function organisationProfile(): HasOne
    {
        return $this->hasOne(OrganisationProfile::class, 'user_uuid');
    }

    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'user_uuid');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
