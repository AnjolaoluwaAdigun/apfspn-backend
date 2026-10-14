<?php

namespace App\Http\Middleware;

use App\Enums\TokenAudience;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Auth\TokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessToken
{
    public function __construct(private readonly TokenService $tokens) {}

    public function handle(Request $request, Closure $next, string $audience, string $kind = 'access'): Response
    {
        $subject = $request->user();
        $token = $subject?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            throw new ApiException('Invalid access token.', 401, ['code' => 'invalid_token']);
        }

        $audience = TokenAudience::from($audience);
        $wantsRefresh = $kind === 'refresh';

        if ($token->isRefresh() && ! $wantsRefresh) {
            throw new ApiException('Refresh tokens cannot be used to access this resource.', 401, ['code' => 'refresh_token_not_allowed']);
        }

        if (! $token->isRefresh() && $wantsRefresh) {
            throw new ApiException('A refresh token is required.', 401, ['code' => 'refresh_token_required']);
        }

        $ability = $wantsRefresh ? $audience->value.':refresh' : $audience->value;
        $subjectClass = $audience === TokenAudience::Admin ? Admin::class : User::class;

        if (! $subject instanceof $subjectClass || ! $token->can($ability)) {
            throw new ApiException('This token cannot be used here.', 403, ['code' => 'wrong_audience']);
        }

        $this->tokens->assertCanAuthenticate($subject);

        return $next($request);
    }
}
