<?php

namespace App\Http\Controllers\Member;

use App\Enums\AuthClient;
use App\Enums\MemberType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\Registration\CreatePasswordRequest;
use App\Http\Requests\Member\Registration\RegisterCorporateRequest;
use App\Http\Requests\Member\Registration\RegisterIndividualRequest;
use App\Http\Requests\Member\Registration\RegisterInstitutionalRequest;
use App\Http\Requests\Member\Registration\ResendVerificationRequest;
use App\Http\Requests\Member\Registration\VerifyEmailRequest;
use App\Http\Resources\AuthResultResource;
use App\Http\Resources\ChallengeResource;
use App\Services\Membership\RegistrationService;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    private const REGISTERED = 'Application submitted. Enter the code we sent to your email to verify it.';

    public function __construct(private readonly RegistrationService $registration) {}

    public function individual(RegisterIndividualRequest $request): JsonResponse
    {
        $result = $this->registration->register(MemberType::Individual, $request->validated());

        return JsonResponser::success(new AuthResultResource($result), self::REGISTERED, 201);
    }

    public function corporate(RegisterCorporateRequest $request): JsonResponse
    {
        $result = $this->registration->register(MemberType::Corporate, $request->validated());

        return JsonResponser::success(new AuthResultResource($result), self::REGISTERED, 201);
    }

    public function institutional(RegisterInstitutionalRequest $request): JsonResponse
    {
        $result = $this->registration->register(MemberType::Institutional, $request->validated());

        return JsonResponser::success(new AuthResultResource($result), self::REGISTERED, 201);
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->registration->verifyEmail($data['challenge_token'], $data['code']);

        return JsonResponser::success(new AuthResultResource($result), 'Email verified. Create your password to continue.');
    }

    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $challenge = $this->registration->resendVerification($request->validated()['email']);

        return JsonResponser::success(new ChallengeResource($challenge), 'If an unverified application exists for this email, a new code has been sent.');
    }

    public function createPassword(CreatePasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->registration->createPassword($data['password_token'], $data['password'], AuthClient::from($data['client']));

        return JsonResponser::success(new AuthResultResource($result), 'Your password has been created.');
    }
}
