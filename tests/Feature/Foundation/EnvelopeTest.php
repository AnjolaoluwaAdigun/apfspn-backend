<?php

namespace Tests\Feature\Foundation;

use App\Exceptions\ApiException;
use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use App\Support\JsonResponser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class EnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/_test')->group(function () {
            Route::post('validation', fn (EnvelopeTestRequest $request) => JsonResponser::success($request->validated()));
            Route::get('users/{user}', fn (User $user) => JsonResponser::success(['uuid' => $user->uuid]));
            Route::get('api-exception', fn () => throw new ApiException('That email is already registered.', 409, ['code' => 'email_taken', 'field' => 'email']));
            Route::get('blocked', fn () => throw ApiException::blocked('This role is still assigned to admins.', ['admins' => 2]));
            Route::get('unexpected', fn () => throw new InvalidArgumentException('internal detail'));
            Route::get('protected', fn () => JsonResponser::success())->middleware('auth:sanctum');
            Route::post('throttled', fn () => JsonResponser::success())->middleware('throttle:login');
        });
    }

    public function test_health_endpoint_returns_success_envelope(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJsonStructure(['error', 'message', 'data' => ['status', 'app', 'version', 'time']])
            ->assertJson(['error' => false, 'data' => ['status' => 'ok', 'version' => 'v1']]);
    }

    public function test_validation_error_renders_first_message_and_field_errors(): void
    {
        $this->postJson('/api/v1/_test/validation', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertExactJsonStructure(['error', 'message', 'data' => ['email', 'name']])
            ->assertJson([
                'error' => true,
                'message' => 'The email field must be a valid email address.',
            ]);
    }

    public function test_unknown_route_renders_404_envelope(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['error' => true, 'message' => 'Resource not found.', 'data' => null]);
    }

    public function test_missing_model_renders_404_envelope(): void
    {
        $this->getJson('/api/v1/_test/users/'.Str::uuid())
            ->assertNotFound()
            ->assertExactJson(['error' => true, 'message' => 'Resource not found.', 'data' => null]);
    }

    public function test_api_exception_renders_its_status_message_and_payload(): void
    {
        $this->getJson('/api/v1/_test/api-exception')
            ->assertStatus(409)
            ->assertExactJson([
                'error' => true,
                'message' => 'That email is already registered.',
                'data' => ['code' => 'email_taken', 'field' => 'email'],
            ]);
    }

    public function test_blocked_delete_renders_code_and_counts(): void
    {
        $this->getJson('/api/v1/_test/blocked')
            ->assertStatus(422)
            ->assertExactJson([
                'error' => true,
                'message' => 'This role is still assigned to admins.',
                'data' => ['code' => 'blocked', 'counts' => ['admins' => 2]],
            ]);
    }

    public function test_api_exception_without_code_is_rejected(): void
    {
        $this->expectException(\LogicException::class);

        new ApiException('Missing code.');
    }

    public function test_unexpected_exception_renders_generic_500_without_leaking_message(): void
    {
        $this->getJson('/api/v1/_test/unexpected')
            ->assertStatus(500)
            ->assertExactJson(['error' => true, 'message' => 'Something went wrong. Please try again later.', 'data' => null]);
    }

    public function test_unauthenticated_request_renders_401_envelope(): void
    {
        $this->getJson('/api/v1/_test/protected')
            ->assertUnauthorized()
            ->assertExactJson(['error' => true, 'message' => 'Unauthenticated.', 'data' => null]);
    }

    public function test_throttled_request_renders_429_with_retry_after(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/_test/throttled', ['email' => 'a@example.com'])->assertOk();
        }

        $this->postJson('/api/v1/_test/throttled', ['email' => 'a@example.com'])
            ->assertStatus(429)
            ->assertJson(['error' => true])
            ->assertJsonStructure(['data' => ['retry_after_seconds']]);
    }
}

class EnvelopeTestRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'name' => ['required', 'string'],
        ];
    }
}
