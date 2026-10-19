<?php

namespace Database\Factories;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    public const PASSWORD = 'Password123';

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->userName().'@apfspn.test',
            'password' => static::$password ??= Hash::make(self::PASSWORD),
            'status' => AdminStatus::Active,
            'must_reset_password' => false,
        ];
    }

    public function mustResetPassword(): static
    {
        return $this->state(fn () => ['must_reset_password' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => AdminStatus::Disabled]);
    }
}
