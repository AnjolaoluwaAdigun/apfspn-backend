<?php

namespace Tests\Feature\Foundation;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Feature\AdminMembers\InteractsWithAdminMembers;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class ExceptionReportingTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_expected_exceptions_from_requests_are_not_reported(): void
    {
        $user = User::factory()->create();
        $associationManager = $this->adminToken('association_manager');

        Exceptions::fake();
        Log::spy();

        $challenge = $this->api('POST', '/api/v1/member/auth/login', ['email' => $user->email, 'password' => 'Password123'])
            ->assertOk()
            ->json('data.challenge.challenge_token');

        $this->api('POST', '/api/v1/member/auth/verify-login-otp', ['challenge_token' => $challenge, 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'invalid_otp');

        $this->api('POST', '/api/v1/member/auth/login', [])->assertStatus(422);
        $this->api('GET', '/api/v1/member/profile')->assertUnauthorized();
        $this->api('GET', '/api/v1/admin/members/export', [], $associationManager)->assertForbidden();
        $this->api('GET', '/api/v1/admin/members/'.fake()->uuid(), [], $associationManager)->assertNotFound();
        $this->api('GET', '/api/v1/does-not-exist')->assertNotFound();

        foreach (range(1, 6) as $attempt) {
            $response = $this->api('POST', '/api/v1/admin/auth/login', ['email' => 'nobody@apfspn.test', 'password' => 'wrong-password']);
        }

        $response->assertStatus(429);

        Exceptions::assertNothingReported();
        Log::shouldNotHaveReceived('error');
    }

    public function test_expected_exception_types_are_in_dont_report(): void
    {
        $handler = app(ExceptionHandler::class);

        foreach ([
            new ApiException('Expected.', 422, ['code' => 'expected']),
            ValidationException::withMessages(['field' => 'Invalid.']),
            new AuthenticationException,
            new AuthorizationException,
            new ModelNotFoundException,
            new NotFoundHttpException,
            new ThrottleRequestsException,
            UnauthorizedException::forPermissions(['members.view']),
        ] as $exception) {
            $this->assertFalse($handler->shouldReport($exception), $exception::class.' should not be reported.');
        }

        $this->assertTrue($handler->shouldReport(new RuntimeException('Unexpected.')));
        $this->assertTrue($handler->shouldReport(new InvalidArgumentException('Unexpected.')));
    }

    public function test_unexpected_exceptions_are_reported_and_logged(): void
    {
        Route::middleware('api')->get('api/v1/testing/explode', fn () => throw new RuntimeException('Unexpected failure.'));

        Log::spy();

        $this->api('GET', '/api/v1/testing/explode')
            ->assertStatus(500)
            ->assertExactJson(['error' => true, 'message' => 'Something went wrong. Please try again later.', 'data' => null]);

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message) => $message === 'Unexpected failure.');
    }
}
