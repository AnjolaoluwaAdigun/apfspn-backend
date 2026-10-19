<?php

namespace Tests\Feature\Foundation;

use App\Enums\ArticleStatus;
use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Enums\MembershipStatus;
use App\Models\Admin;
use App\Models\Article;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    public function test_it_refuses_to_run_outside_local(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DemoSeeder only runs when APP_ENV=local.');

        $this->seed(DemoSeeder::class);
    }

    public function test_it_seeds_every_state_without_sending_anything(): void
    {
        Queue::fake();
        Mail::fake();
        $this->app['env'] = 'local';

        $this->seed(DemoSeeder::class);

        foreach (array_keys(RolesAndPermissionsSeeder::roleMap()) as $role) {
            $this->assertSame(1, Admin::role($role)->count(), $role);
        }

        $statuses = Membership::pluck('status')->map->value->countBy()->all();
        foreach (MembershipStatus::values() as $status) {
            $this->assertArrayHasKey($status, $statuses, $status);
        }

        $this->assertSame('APFSPNLA001', User::where('email', 'adaobi@apfspn.test')->first()->membership->member_id);
        $this->assertSame('corporate', User::where('email', 'tunde@apfspn.test')->first()->membership->member_type->value);
        $this->assertSame(0, User::where('email', 'not like', '%@apfspn.test')->count());
        $this->assertSame(0, Admin::where('email', 'not like', '%@apfspn.test')->count());

        foreach ([EventStatus::Published, EventStatus::Draft, EventStatus::Cancelled, EventStatus::Completed] as $status) {
            $this->assertTrue(Event::where('status', $status)->exists(), $status->value);
        }
        $this->assertSame([1, 2], EventRegistration::where('status', EventRegistrationStatus::Waitlisted)->orderBy('waitlist_position')->pluck('waitlist_position')->all());

        foreach (ArticleStatus::cases() as $status) {
            $this->assertTrue(Article::where('status', $status)->exists(), $status->value);
        }

        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();

        $this->seed(DemoSeeder::class);
        $this->assertSame(6, Admin::count());
    }

    public function test_demo_accounts_can_sign_in(): void
    {
        Queue::fake();
        $this->app['env'] = 'local';
        $this->seed(DemoSeeder::class);

        $this->api('POST', '/api/v1/admin/auth/login', ['email' => 'superadmin@apfspn.test', 'password' => DemoSeeder::ADMIN_PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'authenticated');

        $token = $this->api('POST', '/api/v1/member/auth/login', ['email' => 'adaobi@apfspn.test', 'password' => DemoSeeder::MEMBER_PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'authenticated')
            ->json('data.tokens.access_token');

        $this->api('GET', '/api/v1/member/dashboard', [], $token)
            ->assertOk()
            ->assertJsonPath('data.membership_card.member_id', 'APFSPNLA001')
            ->assertJsonCount(1, 'data.waitlisted_events');
    }
}
