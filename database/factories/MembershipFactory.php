<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Enums\MemberType;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'member_type' => MemberType::Individual,
            'state_code' => 'LA',
            'status' => MembershipStatus::Active,
            'approved_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => MembershipStatus::PendingApproval, 'approved_at' => null]);
    }
}
