<?php

namespace Tests\Feature\Events;

use App\Models\Admin;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

trait InteractsWithEvents
{
    private array $memberTokenCache = [];

    protected function adminWithRole(string $role): Admin
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return tap(Admin::factory()->create())->assignRole($role);
    }

    protected function tokenFor(Admin $admin): string
    {
        return $this->adminTokens($admin)['access_token'];
    }

    protected function memberToken(User $member): string
    {
        return $this->memberTokenCache[$member->uuid] ??= $this->memberTokens($member)['access_token'];
    }

    protected function eventUrl(Event $event, string $suffix = ''): string
    {
        return '/api/v1/admin/events/'.$event->uuid.($suffix !== '' ? '/'.$suffix : '');
    }

    protected function registerUrl(Event $event): string
    {
        return '/api/v1/member/events/'.$event->uuid.'/register';
    }

    protected function eventPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Lagos Food Service Summit 2026',
            'description' => 'A day of talks and workshops for food service professionals.',
            'starts_at' => now()->addDays(30)->setTime(9, 0)->toIso8601String(),
            'ends_at' => now()->addDays(30)->setTime(17, 0)->toIso8601String(),
            'venue' => 'Eko Hotel',
            'address' => 'Plot 1415 Adetokunbo Ademola Street, Victoria Island',
            'city' => 'Lagos',
            'state_code' => 'LA',
            'capacity' => 100,
            'schedule' => [
                ['time' => '09:00', 'title' => 'Registration'],
                ['time' => '10:00', 'title' => 'Keynote', 'description' => 'The future of hospitality in Nigeria'],
            ],
        ];
    }
}
