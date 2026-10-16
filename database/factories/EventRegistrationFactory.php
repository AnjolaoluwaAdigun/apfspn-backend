<?php

namespace Database\Factories;

use App\Enums\EventRegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRegistration>
 */
class EventRegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_uuid' => Event::factory()->published(),
            'user_uuid' => User::factory(),
            'status' => EventRegistrationStatus::Registered,
            'registered_at' => now(),
        ];
    }

    public function waitlisted(int $position): static
    {
        return $this->state(fn () => ['status' => EventRegistrationStatus::Waitlisted, 'waitlist_position' => $position]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => EventRegistrationStatus::Cancelled, 'cancelled_at' => now()]);
    }

    public function attended(): static
    {
        return $this->state(fn () => ['status' => EventRegistrationStatus::Attended, 'attended_at' => now()]);
    }
}
