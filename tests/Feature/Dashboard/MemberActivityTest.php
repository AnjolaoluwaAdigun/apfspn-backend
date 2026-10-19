<?php

namespace Tests\Feature\Dashboard;

use App\Enums\MemberActivityType;
use App\Enums\MembershipStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberActivity;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\Feature\Membership\InteractsWithRegistration;
use Tests\TestCase;

class MemberActivityTest extends TestCase
{
    use InteractsWithAuth, InteractsWithRegistration, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->fakeUploads();
    }

    public function test_registration_and_email_verification_are_recorded(): void
    {
        $this->completeRegistration('individual', ['email' => 'kemi@apfspn.test'])->assertOk();

        $member = User::where('email', 'kemi@apfspn.test')->firstOrFail();

        $this->assertSame(
            [MemberActivityType::ApplicationSubmitted, MemberActivityType::EmailVerified],
            $this->activityTypes($member),
        );
    }

    public function test_profile_update_records_field_names_without_values(): void
    {
        $member = User::factory()->withoutTwoFactor()->create();
        $token = $this->memberTokens($member)['access_token'];

        $this->api('PUT', '/api/v1/member/profile', ['phone' => '08031234567', 'bio' => 'Head chef at Mama Put'], $token)->assertOk();
        $this->api('PUT', '/api/v1/member/profile', ['phone' => '08031234567'], $token)->assertOk();

        $activity = MemberActivity::where('user_uuid', $member->uuid)->sole();
        $raw = DB::table('member_activities')->value('properties');

        $this->assertSame(MemberActivityType::ProfileUpdated, $activity->type);
        $this->assertEqualsCanonicalizing(['phone', 'bio'], $activity->properties['changed_fields']);
        $this->assertStringNotContainsString('08031234567', $raw);
        $this->assertStringNotContainsString('Mama Put', $raw);
    }

    public function test_security_and_password_changes_are_recorded(): void
    {
        $member = User::factory()->withoutTwoFactor()->create();
        $token = $this->memberTokens($member)['access_token'];

        $this->api('PUT', '/api/v1/member/security', ['two_factor_enabled' => true], $token)->assertOk();
        $this->api('PUT', '/api/v1/member/security', ['two_factor_enabled' => true], $token)->assertOk();
        $this->api('POST', '/api/v1/member/auth/change-password', [
            'current_password' => 'Password123',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ], $token)->assertOk();

        $this->assertSame(
            [MemberActivityType::TwoFactorEnabled, MemberActivityType::PasswordChanged],
            $this->activityTypes($member),
        );
    }

    public function test_event_registration_lifecycle_is_recorded(): void
    {
        $first = User::factory()->withoutTwoFactor()->create();
        $second = User::factory()->withoutTwoFactor()->create();
        $event = Event::factory()->published()->capacity(1)->create();
        $url = '/api/v1/member/events/'.$event->uuid.'/register';

        $this->api('POST', $url, [], $this->memberTokens($first)['access_token'])->assertOk();
        $this->api('POST', $url, [], $this->memberTokens($second)['access_token'])->assertOk();
        $this->api('DELETE', $url, [], $this->memberTokens($first)['access_token'])->assertOk();

        $this->assertSame([MemberActivityType::EventRegistered, MemberActivityType::EventRegistrationCancelled], $this->activityTypes($first));
        $this->assertSame([MemberActivityType::EventWaitlisted, MemberActivityType::EventPromoted], $this->activityTypes($second));
        $this->assertSame($event->uuid, MemberActivity::where('user_uuid', $second->uuid)->value('subject_uuid'));
    }

    public function test_admin_actions_on_a_member_are_recorded(): void
    {
        $token = $this->adminTokens($this->superAdmin())['access_token'];
        $member = User::factory()->pendingApproval()->create();
        $event = Event::factory()->published()->started()->create();
        $attendee = User::factory()->create();
        $registration = EventRegistration::factory()->create(['event_uuid' => $event->uuid, 'user_uuid' => $attendee->uuid]);

        $this->api('POST', '/api/v1/admin/members/'.$member->uuid.'/approve', [], $token)->assertOk();
        $this->api('PUT', '/api/v1/admin/members/'.$member->uuid, ['bio' => 'Updated by staff'], $token)->assertOk();
        $this->api('POST', '/api/v1/admin/events/'.$event->uuid.'/attendees/'.$registration->uuid.'/attend', [], $token)->assertOk();

        $this->assertSame([MemberActivityType::MembershipApproved, MemberActivityType::ProfileUpdatedByAdmin], $this->activityTypes($member));
        $this->assertSame(['bio'], MemberActivity::where('type', MemberActivityType::ProfileUpdatedByAdmin)->value('properties')['changed_fields']);
        $this->assertSame([MemberActivityType::EventAttended], $this->activityTypes($attendee));
    }

    public function test_a_rolled_back_action_leaves_no_activity_row(): void
    {
        $token = $this->adminTokens($this->superAdmin())['access_token'];
        $member = User::factory()->pendingApproval()->create();

        $this->mock(AuditLogRepositoryInterface::class)
            ->shouldReceive('create')
            ->andThrow(new RuntimeException('Audit store unavailable.'));

        $this->api('POST', '/api/v1/admin/members/'.$member->uuid.'/approve', [], $token)
            ->assertStatus(500)
            ->assertJsonPath('error', true);

        $this->assertDatabaseCount('member_activities', 0);
        $this->assertSame(MembershipStatus::PendingApproval, $member->membership->refresh()->status);
        $this->assertNull($member->membership->member_id);
    }

    private function activityTypes(User $member): array
    {
        return MemberActivity::where('user_uuid', $member->uuid)
            ->orderBy('created_at')
            ->orderBy('uuid')
            ->get()
            ->map(fn (MemberActivity $activity) => $activity->type)
            ->all();
    }
}
