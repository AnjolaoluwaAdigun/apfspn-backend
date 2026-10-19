<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ePermission;
use App\Enums\MembershipStatus;
use App\Models\Admin;
use App\Models\Article;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    private const URL = '/api/v1/admin/dashboard';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00', 'UTC'));
    }

    public function test_super_admin_sees_every_section(): void
    {
        $token = $this->adminTokens($this->superAdmin())['access_token'];

        User::factory()->pendingApproval()->count(2)->create();
        User::factory()->pendingApproval()->unverified()->create();
        $active = User::factory()->count(3)->create();
        User::factory()->has(Membership::factory()->state(['status' => MembershipStatus::Suspended]))->create();
        $lastMonth = User::factory()->create();
        $lastMonth->membership->forceFill(['created_at' => Carbon::parse('2026-09-20 10:00:00')])->save();
        $mondayInLagos = User::factory()->create();
        $mondayInLagos->membership->forceFill(['created_at' => Carbon::parse('2026-10-11 23:30:00', 'UTC')])->save();
        $lastWeek = User::factory()->create();
        $lastWeek->membership->forceFill(['created_at' => Carbon::parse('2026-10-11 22:30:00', 'UTC')])->save();

        $soonest = Event::factory()->published()->capacity(2)->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2)]);
        foreach (range(2, 6) as $days) {
            Event::factory()->published()->create(['starts_at' => now()->addDays($days), 'ends_at' => now()->addDays($days)->addHours(2)]);
        }
        Event::factory()->create(['starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(4)]);
        Event::factory()->published()->past()->create();
        $registration = fn (User $user) => ['event_uuid' => $soonest->uuid, 'user_uuid' => $user->uuid, 'registered_at' => now()];
        EventRegistration::factory()->create($registration($active[0]));
        EventRegistration::factory()->create($registration($active[1]));
        EventRegistration::factory()->waitlisted(1)->create($registration($active[2]));
        EventRegistration::factory()->cancelled()->create($registration($lastMonth));
        EventRegistration::factory()->create(['user_uuid' => $mondayInLagos->uuid, 'registered_at' => Carbon::parse('2026-10-05 10:00:00')]);

        Article::factory()->create();
        Article::factory()->inReview()->count(2)->create();
        Article::factory()->published()->create();
        Article::factory()->archived()->create();

        $response = $this->api('GET', self::URL, [], $token)
            ->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('data.members.counts_by_status.all', 10)
            ->assertJsonPath('data.members.counts_by_status.pending_approval', 3)
            ->assertJsonPath('data.members.counts_by_status.active', 6)
            ->assertJsonPath('data.members.counts_by_status.suspended', 1)
            ->assertJsonPath('data.members.pending_approvals', 2)
            ->assertJsonPath('data.members.new_registrations.this_week', 8)
            ->assertJsonPath('data.members.new_registrations.this_month', 9)
            ->assertJsonCount(5, 'data.events.upcoming')
            ->assertJsonPath('data.events.upcoming.0.uuid', $soonest->uuid)
            ->assertJsonPath('data.events.upcoming.0.registered_count', 2)
            ->assertJsonPath('data.events.upcoming.0.waitlisted_count', 1)
            ->assertJsonPath('data.events.upcoming.0.seats_left', 0)
            ->assertJsonPath('data.events.upcoming.1.seats_left', null)
            ->assertJsonPath('data.events.registrations_this_week', 3)
            ->assertJsonPath('data.articles.counts_by_status.all', 5)
            ->assertJsonPath('data.articles.counts_by_status.in_review', 2)
            ->assertJsonPath('data.articles.counts_by_status.scheduled', 0)
            ->assertJsonPath('data.articles.awaiting_review.total', 2)
            ->assertJsonCount(2, 'data.articles.awaiting_review.items')
            ->assertJsonCount(1, 'data.recent_activity')
            ->assertJsonPath('data.recent_activity.0.action', 'admin.login');

        $this->assertSame(['members', 'events', 'articles', 'recent_activity'], array_keys($response->json('data')));
    }

    public function test_recent_activity_lists_readable_audit_entries(): void
    {
        $admin = $this->superAdmin(['name' => 'Ngozi Admin']);
        $token = $this->adminTokens($admin)['access_token'];
        $member = User::factory()->pendingApproval()->create(['name' => 'Tunde Bakare']);
        $event = Event::factory()->create(['title' => 'Abuja Chefs Forum']);

        $this->api('POST', '/api/v1/admin/members/'.$member->uuid.'/approve', [], $token)->assertOk();
        $this->api('POST', '/api/v1/admin/events/'.$event->uuid.'/publish', [], $token)->assertOk();

        $this->api('GET', self::URL, [], $token)
            ->assertOk()
            ->assertJsonCount(3, 'data.recent_activity')
            ->assertJsonPath('data.recent_activity.0.action', 'event.published')
            ->assertJsonPath('data.recent_activity.0.description', 'Event published')
            ->assertJsonPath('data.recent_activity.0.subject.type', 'event')
            ->assertJsonPath('data.recent_activity.0.subject.label', 'Abuja Chefs Forum')
            ->assertJsonPath('data.recent_activity.1.action', 'membership.approved')
            ->assertJsonPath('data.recent_activity.1.admin.name', 'Ngozi Admin')
            ->assertJsonPath('data.recent_activity.1.subject.type', 'membership')
            ->assertJsonPath('data.recent_activity.1.subject.label', 'Tunde Bakare')
            ->assertJsonPath('data.recent_activity.2.action', 'admin.login')
            ->assertJsonPath('data.recent_activity.2.subject.label', 'Ngozi Admin');
    }

    public function test_recent_activity_is_limited_to_ten_entries(): void
    {
        $token = $this->adminTokens($this->superAdmin())['access_token'];

        foreach (Event::factory()->count(12)->create() as $event) {
            $this->api('POST', '/api/v1/admin/events/'.$event->uuid.'/publish', [], $token)->assertOk();
        }

        $this->api('GET', self::URL, [], $token)->assertOk()->assertJsonCount(10, 'data.recent_activity');
    }

    public static function roleSections(): array
    {
        $cases = [];

        foreach (RolesAndPermissionsSeeder::roleMap() as $role => $permissions) {
            $has = fn (ePermission $permission) => in_array($permission, $permissions, true);

            $events = array_keys(array_filter([
                'upcoming' => $has(ePermission::EventsView),
                'registrations_this_week' => $has(ePermission::EventRegistrationsView),
            ]));

            $cases[$role] = [$role, array_keys(array_filter([
                'members' => $has(ePermission::MembersView),
                'events' => $events !== [],
                'articles' => $has(ePermission::ArticlesView),
                'recent_activity' => $has(ePermission::AuditLogsView),
            ])), $events];
        }

        return $cases;
    }

    #[DataProvider('roleSections')]
    public function test_sections_follow_the_viewers_permissions(string $role, array $sections, array $eventKeys): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = tap(Admin::factory()->create())->assignRole($role);

        $data = $this->api('GET', self::URL, [], $this->adminTokens($admin)['access_token'])->assertOk()->json('data');

        $this->assertSame($sections, array_keys($data));

        if ($eventKeys !== []) {
            $this->assertSame($eventKeys, array_keys($data['events']));
        }
    }

    public function test_admin_without_dashboard_permission_is_forbidden(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::create(['name' => 'member_viewer', 'guard_name' => 'api'])->givePermissionTo(ePermission::MembersView->value);
        $admin = tap(Admin::factory()->create())->assignRole('member_viewer');

        $this->api('GET', self::URL, [], $this->adminTokens($admin)['access_token'])
            ->assertForbidden()
            ->assertJsonPath('error', true);
    }

    public function test_guests_and_member_tokens_are_refused(): void
    {
        $this->api('GET', self::URL)->assertUnauthorized()->assertJsonPath('error', true);

        $member = User::factory()->withoutTwoFactor()->create();

        $this->api('GET', self::URL, [], $this->memberTokens($member)['access_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'wrong_audience');
    }

    public function test_sections_are_cached_and_cleared_when_records_change(): void
    {
        $token = $this->adminTokens($this->superAdmin())['access_token'];
        $member = User::factory()->pendingApproval()->create();

        $this->api('GET', self::URL, [], $token)->assertJsonPath('data.members.pending_approvals', 1);

        DB::table('memberships')->where('user_uuid', $member->uuid)->update(['status' => MembershipStatus::Active->value]);

        $this->api('GET', self::URL, [], $token)->assertJsonPath('data.members.pending_approvals', 1);

        User::factory()->pendingApproval()->create();

        $this->api('GET', self::URL, [], $token)->assertJsonPath('data.members.pending_approvals', 1)
            ->assertJsonPath('data.members.counts_by_status.active', 1);

        DB::table('memberships')->update(['status' => MembershipStatus::Active->value]);
        $this->travel(61)->seconds();

        $this->api('GET', self::URL, [], $token)->assertJsonPath('data.members.pending_approvals', 0);
    }

    public function test_event_and_article_changes_clear_their_sections(): void
    {
        $token = $this->adminTokens($this->superAdmin())['access_token'];

        $this->api('GET', self::URL, [], $token)
            ->assertJsonCount(0, 'data.events.upcoming')
            ->assertJsonPath('data.articles.counts_by_status.all', 0);

        Event::factory()->published()->create();
        Article::factory()->inReview()->create();

        $this->api('GET', self::URL, [], $token)
            ->assertJsonCount(1, 'data.events.upcoming')
            ->assertJsonPath('data.articles.awaiting_review.total', 1);
    }
}
