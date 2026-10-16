<?php

namespace Tests\Feature\Membership;

use App\Enums\ChallengePurpose;
use App\Enums\MembershipStatus;
use App\Enums\MemberType;
use App\Jobs\SendApplicationReceivedEmail;
use App\Jobs\SendOtpEmail;
use App\Models\Membership;
use App\Models\OrganisationProfile;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use InteractsWithAuth, InteractsWithRegistration, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->fakeUploads();
    }

    public static function types(): array
    {
        return [
            'individual' => ['individual'],
            'corporate' => ['corporate'],
            'institutional' => ['institutional'],
        ];
    }

    public function test_individual_registration_creates_pending_membership_and_sends_verification_code(): void
    {
        $this->register('individual')
            ->assertCreated()
            ->assertJsonPath('error', false)
            ->assertJsonPath('data.next_step', 'verify_email')
            ->assertJsonPath('data.challenge.mode', 'send_new')
            ->assertJsonMissingPath('data.tokens');

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->consent_accepted_at);
        $this->assertSame('LA', $user->state_code);
        $this->assertNotNull($user->photo_url);
        $this->assertNull($user->organisationProfile);
        $this->assertSame(MembershipStatus::PendingApproval, $user->membership->status);
        $this->assertSame(MemberType::Individual, $user->membership->member_type);
        $this->assertNull($user->membership->member_id);

        Queue::assertPushed(SendOtpEmail::class, fn (SendOtpEmail $job) => $job->email === 'ada@example.com' && $job->purpose === ChallengePurpose::EmailVerification);
        Queue::assertNotPushed(SendApplicationReceivedEmail::class);
    }

    public function test_corporate_registration_stores_organisation_with_private_document(): void
    {
        $this->register('corporate')->assertCreated()->assertJsonPath('data.next_step', 'verify_email');

        $organisation = OrganisationProfile::firstOrFail();
        $this->assertSame(MemberType::Corporate, $organisation->type);
        $this->assertSame('Chow Co Limited', $organisation->name);
        $this->assertSame('Catering', $organisation->business_type);
        $this->assertSame(2015, $organisation->year_established);
        $this->assertStringStartsWith('members/documents/', $organisation->document_public_id);
        $this->assertStringEndsWith('.pdf', $organisation->document_public_id);
        $this->assertStringNotContainsString('http', $organisation->document_public_id);
        $this->assertSame(self::PDF, $this->privateFiles->files[$organisation->document_public_id]);
        $this->assertSame([], Storage::disk('cloudinary')->allFiles());

        $this->assertSame(MemberType::Corporate, Membership::firstOrFail()->member_type);
        $this->assertSame('LA', Membership::firstOrFail()->state_code);
    }

    public function test_institutional_registration_stores_organisation_with_private_document(): void
    {
        $this->register('institutional')->assertCreated();

        $organisation = OrganisationProfile::firstOrFail();
        $this->assertSame(MemberType::Institutional, $organisation->type);
        $this->assertSame('Benin Culinary Institute', $organisation->name);
        $this->assertSame('School of Hospitality', $organisation->department);
        $this->assertNull($organisation->business_type);
        $this->assertArrayHasKey($organisation->document_public_id, $this->privateFiles->files);
        $this->assertSame('ED', Membership::firstOrFail()->state_code);
    }

    public function test_document_can_be_sent_as_base64_json(): void
    {
        $this->api('POST', '/api/v1/member/register/corporate', $this->corporatePayload([
            'registration_document' => 'data:application/pdf;base64,'.base64_encode(self::PDF),
        ]))->assertCreated();

        $this->assertCount(1, $this->privateFiles->files);
    }

    public function test_corporate_registration_requires_registration_document(): void
    {
        $this->register('corporate', ['registration_document' => null])
            ->assertStatus(422)
            ->assertJsonPath('error', true)
            ->assertJsonValidationErrors('registration_document', 'data');

        $this->assertDatabaseCount('users', 0);
        $this->assertSame([], $this->privateFiles->files);
    }

    public function test_institutional_registration_requires_letter_of_authorisation(): void
    {
        $this->register('institutional', ['letter_of_authorisation' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('letter_of_authorisation', 'data');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_individual_registration_requires_state_and_city(): void
    {
        $this->register('individual', ['state_code' => null, 'city' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['state_code', 'city'], 'data');

        $this->register('individual', ['state_code' => 'XX'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('state_code', 'data');
    }

    public function test_document_with_wrong_type_or_foreign_url_is_rejected(): void
    {
        $this->register('corporate', ['registration_document' => 'data:text/plain;base64,'.base64_encode('just some text')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('registration_document', 'data');

        $this->register('corporate', ['registration_document' => 'https://res.cloudinary.com/apfspn-test/raw/upload/cac.pdf'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('registration_document', 'data');

        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('types')]
    public function test_consent_is_required(string $type): void
    {
        $this->register($type, ['consent' => '0'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('consent', 'data');
    }

    #[DataProvider('types')]
    public function test_duplicate_email_is_rejected(string $type): void
    {
        $existing = User::factory()->create();

        $this->register($type, ['email' => strtoupper($existing->email)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email', 'data');

        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('types')]
    public function test_client_cannot_set_protected_fields(string $type): void
    {
        $this->register($type, [
            'user_uuid' => '9f1b6c2e-0000-4000-8000-000000000000',
            'status' => 'active',
            'membership_status' => 'active',
            'member_id' => 'APFSPNLA001',
            'member_type' => 'corporate',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_uuid', 'status', 'membership_status', 'member_id', 'member_type'], 'data');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('memberships', 0);
    }

    #[DataProvider('types')]
    public function test_pending_member_can_sign_in_after_verification_and_sees_pending_status(string $type): void
    {
        $result = $this->completeRegistration($type)
            ->assertOk()
            ->assertJsonPath('data.next_step', 'authenticated')
            ->assertJsonPath('data.member.membership.status', 'pending_approval')
            ->assertJsonPath('data.member.member_type', $type);

        $this->api('GET', '/api/v1/member/auth/me', [], $result->json('data.tokens.access_token'))
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'pending_approval')
            ->assertJsonPath('data.membership.member_id', null);

        $user = User::firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        Queue::assertPushed(SendApplicationReceivedEmail::class, 1);

        $login = $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => 'NewPassword1'])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'verify_otp');

        $this->api('POST', '/api/v1/member/auth/verify-login-otp', [
            'challenge_token' => $login->json('data.challenge.challenge_token'),
            'code' => $this->lastOtp(),
        ])
            ->assertOk()
            ->assertJsonPath('data.member.membership.status', 'pending_approval');
    }

    public function test_unverified_member_cannot_sign_in(): void
    {
        $user = User::factory()->unverified()->pendingApproval()->create();

        $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertStatus(403)
            ->assertJsonPath('data.code', 'email_not_verified');

        $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => 'WrongPassword1'])
            ->assertStatus(401)
            ->assertJsonPath('data.code', 'invalid_credentials');
    }

    public function test_freshly_registered_member_cannot_sign_in_before_creating_a_password(): void
    {
        $this->register('individual')->assertCreated();

        $this->api('POST', '/api/v1/member/auth/login', ['email' => 'ada@example.com', 'password' => 'NewPassword1'])
            ->assertStatus(401)
            ->assertJsonPath('data.code', 'invalid_credentials');
    }

    public function test_verify_email_rejects_wrong_code(): void
    {
        $registration = $this->register('individual')->assertCreated();

        $this->api('POST', '/api/v1/member/register/verify-email', [
            'challenge_token' => $registration->json('data.challenge.challenge_token'),
            'code' => $this->lastOtp() === '000000' ? '111111' : '000000',
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_otp');

        $this->assertNull(User::firstOrFail()->email_verified_at);
        Queue::assertNotPushed(SendApplicationReceivedEmail::class);
    }

    public function test_login_challenge_token_cannot_verify_email(): void
    {
        $user = User::factory()->create();
        $login = $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

        $this->api('POST', '/api/v1/member/register/verify-email', [
            'challenge_token' => $login->json('data.challenge.challenge_token'),
            'code' => $this->lastOtp(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_challenge_token');
    }

    public function test_password_token_is_single_use(): void
    {
        $passwordToken = $this->verifyEmailFor($this->register('individual'))->json('data.password_token');
        $body = ['password_token' => $passwordToken, 'password' => 'NewPassword1', 'password_confirmation' => 'NewPassword1'];

        $this->api('POST', '/api/v1/member/register/create-password', $body)->assertOk();

        $this->api('POST', '/api/v1/member/register/create-password', $body)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_password_token');
    }

    public function test_password_reset_token_cannot_be_used_to_create_password_and_vice_versa(): void
    {
        $passwordToken = $this->verifyEmailFor($this->register('individual'))->json('data.password_token');

        $this->api('POST', '/api/v1/member/auth/reset-password', [
            'reset_token' => $passwordToken,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_reset_token');

        $user = User::factory()->create();
        $challenge = $this->api('POST', '/api/v1/member/auth/forgot-password', ['email' => $user->email])->json('data.challenge_token');
        $resetToken = $this->api('POST', '/api/v1/member/auth/verify-otp', ['challenge_token' => $challenge, 'code' => $this->lastOtp()])->json('data.reset_token');

        $this->api('POST', '/api/v1/member/register/create-password', [
            'password_token' => $resetToken,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_password_token');
    }

    public function test_resend_verification_is_opaque_and_only_sends_to_unverified_members(): void
    {
        $this->register('individual')->assertCreated();
        $verified = User::factory()->create();
        $this->travel(2)->minutes();

        $shapes = [];

        foreach (['ada@example.com', $verified->email, 'nobody@example.com'] as $email) {
            $shapes[] = array_keys($this->api('POST', '/api/v1/member/register/resend-verification', ['email' => $email])
                ->assertOk()
                ->assertJsonPath('data.mode', 'send_new')
                ->json('data'));
        }

        $this->assertCount(1, array_unique($shapes, SORT_REGULAR));
        Queue::assertPushed(SendOtpEmail::class, 2);
        Queue::assertPushed(SendOtpEmail::class, fn (SendOtpEmail $job) => $job->email === 'ada@example.com');
        Queue::assertNotPushed(SendOtpEmail::class, fn (SendOtpEmail $job) => in_array($job->email, [$verified->email, 'nobody@example.com'], true));
    }
}
