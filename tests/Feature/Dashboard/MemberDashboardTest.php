<?php

namespace Tests\Feature\Dashboard;

use App\Enums\MembershipStatus;
use App\Enums\MemberType;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\OrganisationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class MemberDashboardTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    private const URL = '/api/v1/member/dashboard';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_active_member_sees_card_events_and_activity(): void
    {
        $member = User::factory()->withoutTwoFactor()
            ->has(Membership::factory()->state([
                'member_id' => 'APFSPNLA007',
                'approved_at' => now()->subMonths(11),
                'expires_at' => now()->addDays(30),
            ]))
            ->create(['name' => 'Adaobi Okafor']);
        $token = $this->memberTokens($member)['access_token'];

        $events = collect(range(1, 6))->map(fn (int $days) => Event::factory()->published()->create([
            'starts_at' => now()->addDays($days),
            'ends_at' => now()->addDays($days)->addHours(3),
        ]));
        $full = Event::factory()->published()->capacity(1)->create(['title' => 'Chefs Masterclass']);
        EventRegistration::factory()->create(['event_uuid' => $full->uuid]);

        $events->reverse()->each(fn (Event $event) => $this->api('POST', '/api/v1/member/events/'.$event->uuid.'/register', [], $token)->assertOk());
        $this->api('POST', '/api/v1/member/events/'.$full->uuid.'/register', [], $token)->assertOk()->assertJsonPath('data.status', 'waitlisted');
        EventRegistration::factory()->create(['user_uuid' => $member->uuid, 'event_uuid' => Event::factory()->published()->past()->create()->uuid]);
        EventRegistration::factory()->cancelled()->create(['user_uuid' => $member->uuid]);

        $this->api('GET', self::URL, [], $token)
            ->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('data.welcome_name', 'Adaobi Okafor')
            ->assertJsonPath('data.organisation_name', null)
            ->assertJsonPath('data.membership_card.member_type', 'individual')
            ->assertJsonPath('data.membership_card.member_id', 'APFSPNLA007')
            ->assertJsonPath('data.membership_card.status', 'active')
            ->assertJsonPath('data.membership_card.days_until_expiry', 30)
            ->assertJsonCount(5, 'data.upcoming_events')
            ->assertJsonPath('data.upcoming_events.0.event.uuid', $events[0]->uuid)
            ->assertJsonPath('data.upcoming_events.4.event.uuid', $events[4]->uuid)
            ->assertJsonCount(1, 'data.waitlisted_events')
            ->assertJsonPath('data.waitlisted_events.0.event.title', 'Chefs Masterclass')
            ->assertJsonPath('data.waitlisted_events.0.waitlist_position', 1)
            ->assertJsonCount(7, 'data.recent_activity')
            ->assertJsonPath('data.recent_activity.0.type', 'event_waitlisted')
            ->assertJsonPath('data.recent_activity.0.subject.type', 'event')
            ->assertJsonPath('data.recent_activity.0.subject.title', 'Chefs Masterclass');
    }

    public function test_pending_corporate_member_sees_pending_card(): void
    {
        $member = User::factory()->withoutTwoFactor()
            ->has(Membership::factory()->pending()->state(['member_type' => MemberType::Corporate]))
            ->create(['state_code' => null, 'city' => null]);
        OrganisationProfile::create([
            'user_uuid' => $member->uuid,
            'type' => MemberType::Corporate,
            'name' => 'Chow Co Limited',
            'registration_number' => 'RC123456',
            'business_type' => 'Catering',
            'address' => '12 Allen Avenue, Ikeja',
            'state_code' => 'LA',
            'document_public_id' => 'members/documents/cac.pdf',
        ]);

        $this->api('GET', self::URL, [], $this->memberTokens($member)['access_token'])
            ->assertOk()
            ->assertJsonPath('data.organisation_name', 'Chow Co Limited')
            ->assertJsonPath('data.membership_card.member_type', 'corporate')
            ->assertJsonPath('data.membership_card.status', 'pending_approval')
            ->assertJsonPath('data.membership_card.member_id', null)
            ->assertJsonPath('data.membership_card.days_until_expiry', null)
            ->assertJsonPath('data.upcoming_events', [])
            ->assertJsonPath('data.waitlisted_events', [])
            ->assertJsonPath('data.recent_activity', []);
    }

    public function test_expired_membership_shows_negative_days(): void
    {
        $member = User::factory()->withoutTwoFactor()
            ->has(Membership::factory()->state([
                'status' => MembershipStatus::Expired,
                'member_id' => 'APFSPNED002',
                'expires_at' => now()->subDays(3),
            ]))
            ->create();

        $this->api('GET', self::URL, [], $this->memberTokens($member)['access_token'])
            ->assertOk()
            ->assertJsonPath('data.membership_card.status', 'expired')
            ->assertJsonPath('data.membership_card.days_until_expiry', -3);
    }

    public function test_guests_and_admin_tokens_are_refused(): void
    {
        $this->api('GET', self::URL)->assertUnauthorized()->assertJsonPath('error', true);

        $this->api('GET', self::URL, [], $this->adminTokens($this->superAdmin())['access_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'wrong_audience');
    }
}
