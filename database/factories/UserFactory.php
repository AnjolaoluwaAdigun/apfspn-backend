<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public const PASSWORD = 'Password123';

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->userName().'@apfspn.test',
            'email_verified_at' => now(),
            'phone' => fake()->unique()->numerify('080########'),
            'password' => static::$password ??= Hash::make(self::PASSWORD),
            'status' => UserStatus::Active,
            'state_code' => 'LA',
            'city' => 'Ikeja',
            'two_factor_enabled' => true,
            'consent_accepted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (! $user->membership()->exists()) {
                Membership::factory()->for($user)->create(['state_code' => $user->state_code ?? 'LA']);
            }
        });
    }

    public function pendingApproval(): static
    {
        return $this->has(Membership::factory()->pending());
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function withoutTwoFactor(): static
    {
        return $this->state(fn () => ['two_factor_enabled' => false]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Disabled]);
    }
}
