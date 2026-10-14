<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CredentialVerifier
{
    private static ?string $dummyHash = null;

    public function assertValid(?Authenticatable $subject, string $password): void
    {
        $hash = $subject?->getAuthPassword() ?? (self::$dummyHash ??= Hash::make(Str::random(40)));
        $matches = Hash::check($password, $hash);

        if (! $subject || ! $matches) {
            throw new ApiException('These credentials do not match our records.', 401, ['code' => 'invalid_credentials']);
        }
    }
}
