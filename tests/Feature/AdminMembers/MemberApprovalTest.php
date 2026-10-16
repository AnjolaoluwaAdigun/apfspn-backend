<?php

namespace Tests\Feature\AdminMembers;

use App\Enums\MembershipNotice;
use App\Enums\MembershipStatus;
use App\Jobs\SendMembershipStatusEmail;
use App\Mail\MembershipStatusMail;
use App\Models\AuditLog;
use App\Models\MemberIdSequence;
use App\Models\User;
use App\Services\Membership\MemberApprovalService;
use App\Services\Membership\MemberIdGenerator;
use App\Services\Membership\MembershipMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class MemberApprovalTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_admin_can_approve_a_pending_member(): void
    {
        $this->freezeSecond();
        $member = $this->pendingMember(['state_code' => 'LA']);

        $this->api('POST', $this->memberUrl($member, 'approve'), [], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('error', false)
            ->assertJsonPath('message', 'Membership approved.')
            ->assertJsonPath('data.membership.status', 'active')
            ->assertJsonPath('data.membership.member_id', 'APFSPNLA001')
            ->assertJsonPath('data.membership.approved_at', now()->toIso8601String())
            ->assertJsonPath('data.membership.expires_at', now()->addYear()->toIso8601String());

        $membership = $member->membership->refresh();
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertNotNull($membership->approved_by_admin_uuid);

        $audit = AuditLog::where('action', 'membership.approved')->sole();
        $this->assertSame($membership->uuid, $audit->auditable_uuid);
        $this->assertEqualsCanonicalizing(['member_id', 'status', 'approved_at', 'approved_by_admin_uuid', 'expires_at'], $audit->changes['changed_fields']);
        $this->assertSame('pending_approval', $audit->changes['previous']['status']);

        Queue::assertPushed(SendMembershipStatusEmail::class, fn ($job) => $job->membershipUuid === $membership->uuid && $job->notice === MembershipNotice::Approved);
    }

    public function test_member_id_format_uses_the_snapshotted_state_and_three_digits(): void
    {
        $member = $this->pendingMember(['state_code' => 'LA'], ['state_code' => 'ED']);

        $this->api('POST', $this->memberUrl($member, 'approve'), [], $this->adminToken())->assertOk();

        $this->assertMatchesRegularExpression('/^APFSPNED\d{3}$/', $member->membership->refresh()->member_id);
        $this->assertSame('APFSPNED001', $member->membership->member_id);
    }

    public function test_approving_twice_is_safe_and_changes_nothing(): void
    {
        $member = $this->pendingMember();
        $token = $this->adminToken();

        $this->api('POST', $this->memberUrl($member, 'approve'), [], $token)->assertOk();
        $first = $member->membership->refresh()->only(['member_id', 'approved_at', 'expires_at', 'updated_at']);

        $this->travel(5)->minutes();

        $this->api('POST', $this->memberUrl($member, 'approve'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.membership.member_id', 'APFSPNLA001');

        $this->assertEquals($first, $member->membership->refresh()->only(['member_id', 'approved_at', 'expires_at', 'updated_at']));
        $this->assertSame(1, MemberIdSequence::where('state_code', 'LA')->value('last_sequence'));
        $this->assertSame(1, AuditLog::where('action', 'membership.approved')->count());
        Queue::assertPushed(SendMembershipStatusEmail::class, 1);
    }

    public function test_member_ids_are_sequential_and_unique_per_state(): void
    {
        $token = $this->adminToken();
        $members = [
            $this->pendingMember(['state_code' => 'LA']),
            $this->pendingMember(['state_code' => 'ED']),
            $this->pendingMember(['state_code' => 'LA']),
            $this->pendingMember(['state_code' => 'LA']),
            $this->pendingMember(['state_code' => 'ED']),
        ];

        foreach ($members as $member) {
            $this->api('POST', $this->memberUrl($member, 'approve'), [], $token)->assertOk();
        }

        $this->assertSame(
            ['APFSPNLA001', 'APFSPNED001', 'APFSPNLA002', 'APFSPNLA003', 'APFSPNED002'],
            array_map(fn (User $member) => $member->membership->refresh()->member_id, $members),
        );
    }

    public function test_generator_creates_a_missing_sequence_row(): void
    {
        MemberIdSequence::where('state_code', 'KN')->delete();

        $ids = DB::transaction(fn () => [app(MemberIdGenerator::class)->next('KN'), app(MemberIdGenerator::class)->next('KN')]);

        $this->assertSame(['APFSPNKN001', 'APFSPNKN002'], $ids);
    }

    public function test_sequence_grows_past_three_digits(): void
    {
        MemberIdSequence::where('state_code', 'LA')->update(['last_sequence' => 999]);

        $this->assertSame('APFSPNLA1000', DB::transaction(fn () => app(MemberIdGenerator::class)->next('LA')));
    }

    public function test_member_with_unverified_email_cannot_be_approved(): void
    {
        $member = $this->pendingMember(['email_verified_at' => null]);

        $this->api('POST', $this->memberUrl($member, 'approve'), [], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'email_not_verified');

        $this->assertNull($member->membership->refresh()->member_id);
    }

    public function test_approve_prohibits_client_supplied_membership_fields(): void
    {
        $member = $this->pendingMember();

        $this->api('POST', $this->memberUrl($member, 'approve'), ['member_id' => 'APFSPNLA777', 'expires_at' => '2030-01-01'], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member_id', 'expires_at'], 'data');
    }

    public function test_admin_can_reject_a_pending_member_with_a_reason(): void
    {
        $member = $this->pendingMember();

        $this->api('POST', $this->memberUrl($member, 'reject'), ['reason' => 'Registration document is unreadable.'], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'rejected')
            ->assertJsonPath('data.membership.rejection_reason', 'Registration document is unreadable.')
            ->assertJsonPath('data.membership.member_id', null);

        $this->assertNotNull($member->membership->refresh()->rejected_at);
        $this->assertSame(1, AuditLog::where('action', 'membership.rejected')->count());
        Queue::assertPushed(SendMembershipStatusEmail::class, fn ($job) => $job->notice === MembershipNotice::Rejected);
    }

    public function test_reject_requires_a_reason(): void
    {
        $member = $this->pendingMember();

        $this->api('POST', $this->memberUrl($member, 'reject'), [], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonPath('error', true)
            ->assertJsonValidationErrors(['reason'], 'data');

        $this->assertSame(MembershipStatus::PendingApproval, $member->membership->refresh()->status);
    }

    public function test_approve_or_reject_on_a_non_pending_member_fails(): void
    {
        $token = $this->adminToken();
        $rejected = $this->pendingMember([], ['status' => MembershipStatus::Rejected]);
        $suspended = $this->activeMember([], ['status' => MembershipStatus::Suspended]);
        $active = $this->activeMember();

        $this->api('POST', $this->memberUrl($rejected, 'approve'), [], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_status_transition')
            ->assertJsonPath('data.current_status', 'rejected');

        $this->api('POST', $this->memberUrl($suspended, 'approve'), [], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_status_transition');

        $this->api('POST', $this->memberUrl($active, 'reject'), ['reason' => 'Changed our mind.'], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_status_transition')
            ->assertJsonPath('data.current_status', 'active');

        $this->api('POST', $this->memberUrl($rejected, 'reject'), ['reason' => 'Again.'], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_status_transition');

        Queue::assertNothingPushed();
    }

    public function test_active_member_can_be_suspended_and_reactivated(): void
    {
        $member = $this->activeMember();
        $token = $this->adminToken();

        $this->api('POST', $this->memberUrl($member, 'suspend'), ['reason' => 'Code of conduct breach.'], $token)
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'suspended')
            ->assertJsonPath('data.membership.suspension_reason', 'Code of conduct breach.');

        $this->api('POST', $this->memberUrl($member, 'reactivate'), [], $token)
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'active')
            ->assertJsonPath('data.membership.member_id', $member->membership->member_id);

        $this->assertNotNull($member->membership->refresh()->reactivated_at);
        $this->assertSame(1, AuditLog::where('action', 'membership.suspended')->count());
        $this->assertSame(1, AuditLog::where('action', 'membership.reactivated')->count());
        Queue::assertPushed(SendMembershipStatusEmail::class, fn ($job) => $job->notice === MembershipNotice::Suspended);
        Queue::assertPushed(SendMembershipStatusEmail::class, fn ($job) => $job->notice === MembershipNotice::Reactivated);
    }

    public function test_suspend_and_reactivate_rules(): void
    {
        $token = $this->adminToken();
        $pending = $this->pendingMember();
        $active = $this->activeMember();

        $this->api('POST', $this->memberUrl($active, 'suspend'), [], $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason'], 'data');

        $this->api('POST', $this->memberUrl($pending, 'suspend'), ['reason' => 'Not allowed.'], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_status_transition');

        $this->api('POST', $this->memberUrl($active, 'reactivate'), [], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_status_transition')
            ->assertJsonPath('data.current_status', 'active');

        $this->api('POST', $this->memberUrl($pending, 'reactivate'), [], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_status_transition');
    }

    public function test_reactivating_after_expiry_marks_the_membership_expired(): void
    {
        $member = $this->activeMember([], ['status' => MembershipStatus::Suspended, 'expires_at' => now()->subDay()]);

        $this->api('POST', $this->memberUrl($member, 'reactivate'), [], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'expired');
    }

    public function test_unknown_member_returns_404(): void
    {
        $this->api('POST', '/api/v1/admin/members/'.fake()->uuid().'/approve', [], $this->adminToken())
            ->assertNotFound()
            ->assertJsonPath('error', true);
    }

    public function test_member_sees_member_id_and_dates_after_approval(): void
    {
        $member = $this->pendingMember(['two_factor_enabled' => false]);
        $memberToken = $this->memberTokens($member)['access_token'];

        $this->api('GET', '/api/v1/member/profile', [], $memberToken)
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'pending_approval')
            ->assertJsonPath('data.membership.member_id', null)
            ->assertJsonMissingPath('data.membership.approved_by_admin_uuid');

        $this->api('POST', $this->memberUrl($member, 'approve'), [], $this->adminToken())->assertOk();
        $membership = $member->membership->refresh();

        foreach (['/api/v1/member/profile', '/api/v1/member/auth/me'] as $uri) {
            $this->api('GET', $uri, [], $memberToken)
                ->assertOk()
                ->assertJsonPath('data.membership.member_id', 'APFSPNLA001')
                ->assertJsonPath('data.membership.status', 'active')
                ->assertJsonPath('data.membership.approved_at', $membership->approved_at->toIso8601String())
                ->assertJsonPath('data.membership.expires_at', $membership->expires_at->toIso8601String());
        }
    }

    public function test_status_emails_are_unique_per_membership_and_action(): void
    {
        $member = $this->activeMember();
        $approved = new SendMembershipStatusEmail($member->membership->uuid, MembershipNotice::Approved, '100');
        $suspended = new SendMembershipStatusEmail($member->membership->uuid, MembershipNotice::Suspended, '100');
        $suspendedAgain = new SendMembershipStatusEmail($member->membership->uuid, MembershipNotice::Suspended, '200');

        $this->assertNotSame($approved->uniqueId(), $suspended->uniqueId());
        $this->assertNotSame($suspended->uniqueId(), $suspendedAgain->uniqueId());
        $this->assertSame($suspended->uniqueId(), (new SendMembershipStatusEmail($member->membership->uuid, MembershipNotice::Suspended, '100'))->uniqueId());
    }

    public function test_status_emails_carry_member_id_and_reason(): void
    {
        Mail::fake();
        $member = $this->pendingMember();
        $approvals = app(MemberApprovalService::class);
        $mailer = app(MembershipMailService::class);

        $approvals->approve($member->uuid);
        (new SendMembershipStatusEmail($member->membership->uuid, MembershipNotice::Approved, '1'))->handle($mailer);

        Mail::assertSent(MembershipStatusMail::class, function (MembershipStatusMail $mail) use ($member) {
            return $mail->hasTo($member->email)
                && $mail->notice === MembershipNotice::Approved
                && str_contains($mail->render(), 'APFSPNLA001');
        });

        $approvals->suspend($member->uuid, 'Unpaid levy.');
        (new SendMembershipStatusEmail($member->membership->uuid, MembershipNotice::Suspended, '2'))->handle($mailer);

        Mail::assertSent(MembershipStatusMail::class, fn (MembershipStatusMail $mail) => $mail->notice === MembershipNotice::Suspended && str_contains($mail->render(), 'Unpaid levy.'));
    }
}
