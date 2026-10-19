<?php

namespace Tests\Feature\Foundation;

use App\Http\Requests\Admin\Auth\ResetPasswordRequest as AdminResetPasswordRequest;
use App\Http\Requests\Member\Auth\ChangePasswordRequest;
use App\Http\Requests\Member\Auth\ResetPasswordRequest as MemberResetPasswordRequest;
use App\Http\Requests\Member\Registration\CreatePasswordRequest;
use App\Http\Requests\Member\Security\UpdateSecurityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class AccountFieldRulesTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    private const MEMBER_PASSWORD_FIELDS = ['uuid', 'user_uuid', 'email', 'email_verified_at', 'status', 'two_factor_enabled', 'member_id', 'membership_status'];

    public static function requests(): array
    {
        $password = ['password' => 'BrandNewPass1', 'password_confirmation' => 'BrandNewPass1'];

        return [
            'member change password' => [ChangePasswordRequest::class, 'POST', '/api/v1/member/auth/change-password', true, $password + ['current_password' => 'Password123'], self::MEMBER_PASSWORD_FIELDS],
            'member reset password' => [MemberResetPasswordRequest::class, 'POST', '/api/v1/member/auth/reset-password', false, $password + ['reset_token' => 'token'], self::MEMBER_PASSWORD_FIELDS],
            'member create password' => [CreatePasswordRequest::class, 'POST', '/api/v1/member/register/create-password', false, $password + ['password_token' => 'token'], self::MEMBER_PASSWORD_FIELDS],
            'member update security' => [UpdateSecurityRequest::class, 'PUT', '/api/v1/member/security', true, ['two_factor_enabled' => true], ['uuid', 'user_uuid', 'email', 'email_verified_at', 'status', 'member_id', 'membership_status', 'password']],
            'admin reset password' => [AdminResetPasswordRequest::class, 'POST', '/api/v1/admin/auth/reset-password', false, $password + ['reset_token' => 'token'], ['uuid', 'admin_uuid', 'email', 'status', 'must_reset_password', 'role_uuid', 'roles', 'permissions']],
        ];
    }

    #[DataProvider('requests')]
    public function test_account_fields_are_prohibited(string $request, string $method, string $url, bool $needsMember, array $payload, array $prohibited): void
    {
        Queue::fake();

        $rules = (new $request)->rules();
        $this->assertEqualsCanonicalizing($prohibited, array_keys(array_filter($rules, fn (array $rule) => $rule === ['prohibited'])));

        $token = $needsMember ? $this->memberTokens(User::factory()->withoutTwoFactor()->create())['access_token'] : null;
        $response = $this->api($method, $url, $payload + array_fill_keys($prohibited, 'x'), $token)
            ->assertUnprocessable()
            ->assertJsonPath('error', true);

        foreach ($prohibited as $field) {
            $this->assertArrayHasKey($field, $response->json('data'), $field.' was not refused');
        }
    }
}
