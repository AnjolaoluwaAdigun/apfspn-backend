<?php

namespace App\Models;

use App\Enums\AdminStatus;
use App\Models\Concerns\AuditsContentChanges;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use AuditsContentChanges, HasApiTokens, HasFactory, HasRoles, HasUuidPrimaryKey, Notifiable;

    protected string $guard_name = 'api';

    protected $rememberTokenName = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'must_reset_password',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => AdminStatus::class,
            'must_reset_password' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === AdminStatus::Active;
    }
}
