<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuidPrimaryKey;

    public const REFRESH_SUFFIX = ':refresh';

    public function isRefresh(): bool
    {
        return Str::endsWith($this->name, self::REFRESH_SUFFIX);
    }

    public function sessionName(): string
    {
        return $this->isRefresh() ? Str::beforeLast($this->name, self::REFRESH_SUFFIX) : $this->name;
    }
}
