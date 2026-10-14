<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuthClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\ForgotPasswordRequest;
use App\Http\Requests\Admin\Auth\LoginRequest;
use App\Http\Requests\Admin\Auth\LogoutRequest;
use App\Http\Requests\Admin\Auth\MeRequest;
use App\Http\Requests\Admin\Auth\RefreshTokenRequest;
use App\Http\Requests\Admin\Auth\ResendOtpRequest;
use App\Http\Requests\Admin\Auth\ResetPasswordRequest;
use App\Http\Requests\Admin\Auth\VerifyOtpRequest;
use App\Http\Resources\AdminResource;
use App\Http\Resources\AuthResultResource;
use App\Http\Resources\AuthTokensResource;
use App\Http\Resources\ChallengeResource;
use App\Services\Auth\AdminAuthService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(private readonly AdminAuthService $auth) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->auth->login($data['email'], $data['password'], AuthClient::from($data['client']));

        return JsonResponser::success(new AuthResultResource($result), 'Login successful.');
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->auth->verifyOtp($data['challenge_token'], $data['code'], AuthClient::from($data['client']));

        return JsonResponser::success(new AuthResultResource($result), 'Code verified.');
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $challenge = $this->auth->resendOtp($request->validated()['challenge_token']);

        return JsonResponser::success(new ChallengeResource($challenge), 'If the code was due, a new one has been sent.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $challenge = $this->auth->forgotPassword($request->validated()['email']);

        return JsonResponser::success(new ChallengeResource($challenge), 'If an account exists for this email, a reset code has been sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->auth->resetPassword($data['reset_token'], $data['password']);

        return JsonResponser::success(null, 'Your password has been reset. Please sign in.');
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->auth->refresh($request->user()->currentAccessToken());

        return JsonResponser::success(new AuthTokensResource($tokens), 'Tokens refreshed.');
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $this->auth->logout($request->user()->currentAccessToken());

        return JsonResponser::success(null, 'Logged out.');
    }

    public function me(MeRequest $request): JsonResponse
    {
        return JsonResponser::success(new AdminResource($this->auth->me($request->user())));
    }
}
