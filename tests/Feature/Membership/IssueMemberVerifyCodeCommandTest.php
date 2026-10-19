<?php

namespace Tests\Feature\Membership;

use App\Jobs\SendOtpEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class IssueMemberVerifyCodeCommandTest extends TestCase
{
    use InteractsWithAuth, InteractsWithRegistration, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->fakeUploads();
    }

    public function test_prints_a_fresh_code_and_token_that_verify_the_email_without_sending_mail(): void
    {
        $this->app['env'] = 'local';
        $registration = $this->register('individual')->assertCreated();
        Queue::fake();

        $this->assertSame(0, Artisan::call('apfspn:member-verify-code', ['email' => 'ada@apfspn.test']));
        $lines = preg_split('/\R/', trim(Artisan::output()));

        $this->assertCount(2, $lines);
        $this->assertMatchesRegularExpression('/^code: \d{6}$/', $lines[0]);
        $this->assertStringStartsWith('challenge_token: ', $lines[1]);
        Queue::assertNotPushed(SendOtpEmail::class);

        $this->api('POST', '/api/v1/member/register/verify-email', [
            'challenge_token' => $registration->json('data.challenge.challenge_token'),
            'code' => substr($lines[0], 6),
        ])->assertStatus(409)->assertJsonPath('data.code', 'stale_challenge_token');

        $this->api('POST', '/api/v1/member/register/verify-email', [
            'challenge_token' => substr($lines[1], 17),
            'code' => substr($lines[0], 6),
        ])->assertOk()->assertJsonPath('data.next_step', 'create_password');

        $this->assertNotNull(User::firstOrFail()->email_verified_at);
    }

    public function test_skips_the_resend_cooldown(): void
    {
        $this->app['env'] = 'local';
        $this->register('individual')->assertCreated();

        $this->assertSame(0, Artisan::call('apfspn:member-verify-code', ['email' => 'ada@apfspn.test']));
        $this->assertSame(0, Artisan::call('apfspn:member-verify-code', ['email' => 'ada@apfspn.test']));
    }

    public function test_refuses_outside_local(): void
    {
        $this->register('individual')->assertCreated();

        foreach (['testing', 'production', 'staging'] as $env) {
            $this->app['env'] = $env;

            $this->artisan('apfspn:member-verify-code', ['email' => 'ada@apfspn.test'])
                ->expectsOutput('This command only runs when APP_ENV=local.')
                ->doesntExpectOutputToContain('code:')
                ->assertFailed();
        }
    }

    public function test_fails_for_unknown_or_verified_email(): void
    {
        $this->app['env'] = 'local';
        $verified = User::factory()->create();

        foreach (['nobody@apfspn.test', $verified->email] as $email) {
            $this->artisan('apfspn:member-verify-code', ['email' => $email])
                ->expectsOutput('No member awaiting email verification was found with that email.')
                ->assertFailed();
        }
    }
}
