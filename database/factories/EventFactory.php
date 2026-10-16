<?php

namespace Database\Factories;

use App\Enums\EventAudience;
use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);
        $startsAt = now()->addDays(14)->setTime(10, 0);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'description' => fake()->paragraph(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(6),
            'venue' => 'Eko Hotel',
            'address' => 'Plot 1415 Adetokunbo Ademola Street, Victoria Island',
            'city' => 'Lagos',
            'state_code' => 'LA',
            'is_virtual' => false,
            'audience' => EventAudience::Public,
            'capacity' => null,
            'status' => EventStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Published, 'published_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => EventStatus::Cancelled,
            'published_at' => now()->subDay(),
            'cancelled_at' => now(),
            'cancellation_reason' => 'Venue unavailable.',
        ]);
    }

    public function completed(): static
    {
        return $this->past()->state(fn () => ['status' => EventStatus::Completed, 'published_at' => now()->subMonth()]);
    }

    public function past(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHours(6)]);
    }

    public function started(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(5)]);
    }

    public function membersOnly(): static
    {
        return $this->state(fn () => ['audience' => EventAudience::MembersOnly]);
    }

    public function virtual(): static
    {
        return $this->state(fn () => [
            'is_virtual' => true,
            'virtual_link' => 'https://zoom.us/j/1234567890',
            'venue' => null,
            'address' => null,
        ]);
    }

    public function capacity(?int $capacity): static
    {
        return $this->state(fn () => ['capacity' => $capacity]);
    }
}
