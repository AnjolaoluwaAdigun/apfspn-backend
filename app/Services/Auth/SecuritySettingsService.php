<?php

namespace App\Services\Auth;

use App\Enums\MemberActivityType;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Membership\MemberActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SecuritySettingsService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly MemberActivityService $activities,
    ) {}

    public function show(User $user): array
    {
        return ['two_factor_enabled' => $user->two_factor_enabled];
    }

    public function update(User $user, bool $twoFactorEnabled, ?string $currentPassword): array
    {
        $locked = DB::transaction(function () use ($user, $twoFactorEnabled, $currentPassword) {
            $locked = $this->users->findForUpdate($user->getKey());

            if (! $twoFactorEnabled && $locked->two_factor_enabled && ! Hash::check((string) $currentPassword, $locked->password)) {
                throw new ApiException('Your current password is incorrect.', 422, ['code' => 'invalid_current_password']);
            }

            $this->users->update($locked, ['two_factor_enabled' => $twoFactorEnabled]);

            if ($locked->wasChanged('two_factor_enabled')) {
                $this->activities->record($locked->getKey(), $twoFactorEnabled ? MemberActivityType::TwoFactorEnabled : MemberActivityType::TwoFactorDisabled);
            }

            return $locked;
        });

        return $this->show($locked);
    }
}
