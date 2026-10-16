<?php

namespace Tests\Feature\AdminMembers;

use App\Enums\MembershipStatus;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Membership\MemberApprovalService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class SuspendedMemberAccessTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public static function blockedStatuses(): array
    {
        return [
            'suspended' => [MembershipStatus::Suspended, 'membership_suspended'],
            'rejected' => [MembershipStatus::Rejected, 'membership_rejected'],
        ];
    }

    public static function allowedStatuses(): array
    {
        return [
            'pending approval' => [MembershipStatus::PendingApproval],
            'expired' => [MembershipStatus::Expired],
            'active' => [MembershipStatus::Active],
        ];
    }

    public function test_suspending_revokes_every_token_of_the_member(): void
    {
        $member = $this->activeMember(['two_factor_enabled' => false]);
        $web = $this->memberTokens($member);
        $mobile = $this->memberTokens($member);
        $other = $this->activeMember(['two_factor_enabled' => false]);
        $otherTokens = $this->memberTokens($other);

        $this->api('POST', $this->memberUrl($member, 'suspend'), ['reason' => 'Code of conduct breach.'], $this->adminToken())->assertOk();

        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $member->uuid)->count());
        $this->assertSame(2, PersonalAccessToken::where('tokenable_id', $other->uuid)->count());

        foreach ([$web, $mobile] as $tokens) {
            $this->api('GET', '/api/v1/member/profile', [], $tokens['access_token'])->assertUnauthorized();
            $this->api('POST', '/api/v1/member/auth/refresh', [], $tokens['refresh_token'])->assertUnauthorized();
        }

        $this->api('GET', '/api/v1/member/profile', [], $otherTokens['access_token'])->assertOk();
    }

    public function test_tokens_are_kept_when_suspension_is_refused(): void
    {
        $member = $this->pendingMember(['two_factor_enabled' => false]);
        $this->memberTokens($member);

        $this->api('POST', $this->memberUrl($member, 'suspend'), ['reason' => 'Not allowed.'], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_status_transition');

        $this->assertSame(2, PersonalAccessToken::where('tokenable_id', $member->uuid)->count());
    }

    public function test_token_revocation_rolls_back_with_a_failed_suspension(): void
    {
        $member = $this->activeMember(['two_factor_enabled' => false]);
        $this->memberTokens($member);

        $this->mock(AuditLogService::class, function (MockInterface $mock) {
            $mock->shouldReceive('currentAdminUuid')->andReturn(null);
            $mock->shouldReceive('changeSet')->andReturn(['changed_fields' => ['status'], 'previous' => []]);
            $mock->shouldReceive('record')->andThrow(new RuntimeException('Audit store unavailable.'));
        });

        try {
            app(MemberApprovalService::class)->suspend($member->uuid, 'Unpaid levy.');
            $this->fail('The suspension should have failed.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit store unavailable.', $e->getMessage());
        }

        $this->assertSame(MembershipStatus::Active, $member->membership->refresh()->status);
        $this->assertSame(2, PersonalAccessToken::where('tokenable_id', $member->uuid)->count());
    }

    #[DataProvider('blockedStatuses')]
    public function test_login_is_refused(MembershipStatus $status, string $code): void
    {
        $withoutTwoFactor = $this->memberWithStatus($status, ['two_factor_enabled' => false]);
        $withTwoFactor = $this->memberWithStatus($status);

        foreach ([$withoutTwoFactor, $withTwoFactor] as $member) {
            $this->api('POST', '/api/v1/member/auth/login', ['email' => $member->email, 'password' => UserFactory::PASSWORD])
                ->assertForbidden()
                ->assertJsonPath('error', true)
                ->assertJsonPath('data.code', $code);
        }

        $this->assertSame(0, PersonalAccessToken::count());
        Queue::assertNothingPushed();
    }

    #[DataProvider('blockedStatuses')]
    public function test_wrong_password_does_not_reveal_the_membership_status(MembershipStatus $status): void
    {
        $member = $this->memberWithStatus($status);

        $this->api('POST', '/api/v1/member/auth/login', ['email' => $member->email, 'password' => 'WrongPassword1'])
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'invalid_credentials');
    }

    #[DataProvider('blockedStatuses')]
    public function test_login_otp_verification_is_refused(MembershipStatus $status, string $code): void
    {
        $member = $this->activeMember();

        $challenge = $this->api('POST', '/api/v1/member/auth/login', ['email' => $member->email, 'password' => UserFactory::PASSWORD])
            ->assertOk()
            ->json('data.challenge.challenge_token');

        $member->membership->update(['status' => $status]);

        $this->api('POST', '/api/v1/member/auth/verify-login-otp', ['challenge_token' => $challenge, 'code' => $this->lastOtp()])
            ->assertForbidden()
            ->assertJsonPath('data.code', $code);

        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[DataProvider('blockedStatuses')]
    public function test_token_refresh_is_refused(MembershipStatus $status, string $code): void
    {
        $member = $this->activeMember(['two_factor_enabled' => false]);
        $tokens = $this->memberTokens($member);

        $member->membership->update(['status' => $status]);

        $this->api('POST', '/api/v1/member/auth/refresh', [], $tokens['refresh_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', $code);
    }

    #[DataProvider('blockedStatuses')]
    public function test_tokens_issued_before_the_status_change_stop_working(MembershipStatus $status, string $code): void
    {
        $member = $this->activeMember(['two_factor_enabled' => false]);
        $tokens = $this->memberTokens($member);

        $this->api('GET', '/api/v1/member/profile', [], $tokens['access_token'])->assertOk();

        $member->membership->update(['status' => $status]);

        foreach (['/api/v1/member/profile', '/api/v1/member/auth/me', '/api/v1/member/security'] as $uri) {
            $this->api('GET', $uri, [], $tokens['access_token'])
                ->assertForbidden()
                ->assertJsonPath('data.code', $code);
        }
    }

    #[DataProvider('allowedStatuses')]
    public function test_pending_expired_and_active_members_can_still_sign_in(MembershipStatus $status): void
    {
        $member = $this->memberWithStatus($status);

        $tokens = $this->memberTokens($member);

        $this->api('GET', '/api/v1/member/profile', [], $tokens['access_token'])
            ->assertOk()
            ->assertJsonPath('data.membership.status', $status->value);

        $this->api('POST', '/api/v1/member/auth/refresh', [], $tokens['refresh_token'])->assertOk();
    }

    public function test_reactivation_does_not_restore_old_tokens(): void
    {
        $member = $this->activeMember(['two_factor_enabled' => false]);
        $before = $this->memberTokens($member);
        $admin = $this->adminToken();

        $this->api('POST', $this->memberUrl($member, 'suspend'), ['reason' => 'Unpaid levy.'], $admin)->assertOk();
        $this->api('POST', '/api/v1/member/auth/login', ['email' => $member->email, 'password' => UserFactory::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'membership_suspended');

        $this->api('POST', $this->memberUrl($member, 'reactivate'), [], $admin)->assertOk();

        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $member->uuid)->count());
        $this->api('GET', '/api/v1/member/profile', [], $before['access_token'])->assertUnauthorized();
        $this->api('POST', '/api/v1/member/auth/refresh', [], $before['refresh_token'])->assertUnauthorized();

        $after = $this->memberTokens($member);

        $this->api('GET', '/api/v1/member/profile', [], $after['access_token'])
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'active');
    }

    private function memberWithStatus(MembershipStatus $status, array $user = []): User
    {
        return $status === MembershipStatus::PendingApproval || $status === MembershipStatus::Rejected
            ? $this->pendingMember($user, ['status' => $status])
            : $this->activeMember($user, ['status' => $status]);
    }
}
