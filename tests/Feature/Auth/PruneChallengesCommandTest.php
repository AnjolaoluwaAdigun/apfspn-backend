<?php

namespace Tests\Feature\Auth;

use App\Enums\ChallengePurpose;
use App\Models\AuthChallenge;
use App\Models\User;
use App\Services\Auth\ChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PruneChallengesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_without_deleting_and_real_run_deletes(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        app(ChallengeService::class)->create($user, User::class, ChallengePurpose::PasswordReset, $user->email);

        $this->travel(2)->days();

        $this->artisan('auth:prune-challenges', ['--dry-run' => true])
            ->expectsOutput('Would delete 1 auth challenges.')
            ->assertSuccessful();
        $this->assertSame(1, AuthChallenge::count());

        $this->artisan('auth:prune-challenges')
            ->expectsOutput('Deleted 1 auth challenges.')
            ->assertSuccessful();
        $this->assertSame(0, AuthChallenge::count());
    }

    public function test_active_challenges_are_kept(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        app(ChallengeService::class)->create($user, User::class, ChallengePurpose::PasswordReset, $user->email);

        $this->artisan('auth:prune-challenges')->expectsOutput('Deleted 0 auth challenges.');
        $this->assertSame(1, AuthChallenge::count());
    }
}
