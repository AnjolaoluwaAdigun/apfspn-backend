<?php

namespace App\Services\Membership;

use App\Enums\AuthClient;
use App\Enums\ChallengePurpose;
use App\Enums\MemberActivityType;
use App\Enums\MembershipStatus;
use App\Enums\MemberType;
use App\Enums\TokenAudience;
use App\Helpers\FileUploadHelper;
use App\Jobs\SendApplicationReceivedEmail;
use App\Models\User;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use App\Repositories\Contracts\OrganisationProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\ChallengeService;
use App\Services\Auth\PasswordResetService;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly MembershipRepositoryInterface $memberships,
        private readonly OrganisationProfileRepositoryInterface $organisations,
        private readonly ChallengeService $challenges,
        private readonly PasswordResetService $passwords,
        private readonly TokenService $tokens,
        private readonly MemberActivityService $activities,
    ) {}

    public function register(MemberType $type, array $data): array
    {
        $photo = FileUploadHelper::upload($data['photo'] ?? null, config('apfspn.uploads.folders.member_photos'));
        $document = $type === MemberType::Individual
            ? null
            : FileUploadHelper::upload($this->document($type, $data), config('apfspn.uploads.folders.registration_documents'), private: true);

        $user = DB::transaction(function () use ($type, $data, $photo, $document) {
            $isIndividual = $type === MemberType::Individual;

            $user = $this->users->create([
                'name' => $data['name'],
                'email' => Str::lower(trim($data['email'])),
                'phone' => $data['phone'],
                'password' => Str::random(64),
                'bio' => $data['bio'] ?? null,
                'photo_url' => $photo,
                'state_code' => $isIndividual ? $data['state_code'] : null,
                'city' => $isIndividual ? $data['city'] : null,
                'consent_accepted_at' => now(),
            ]);

            if (! $isIndividual) {
                $this->organisations->create($this->organisationAttributes($type, $data) + [
                    'user_uuid' => $user->getKey(),
                    'document_public_id' => $document,
                ]);
            }

            $membership = $this->memberships->create([
                'user_uuid' => $user->getKey(),
                'member_type' => $type,
                'state_code' => $data['state_code'],
                'status' => MembershipStatus::PendingApproval,
            ]);

            $this->activities->record($user->getKey(), MemberActivityType::ApplicationSubmitted, $membership);

            return $user;
        });

        return [
            'next_step' => 'verify_email',
            'challenge' => $this->challenges->create($user, User::class, ChallengePurpose::EmailVerification, $user->email),
        ];
    }

    public function verifyEmail(string $token, string $code): array
    {
        $subject = $this->challenges->verify($token, $code, [ChallengePurpose::EmailVerification], User::class)->subject;

        $user = DB::transaction(function () use ($subject) {
            $user = $this->users->findForUpdate($subject->getKey());

            if (! $user->hasVerifiedEmail()) {
                $this->users->update($user, ['email_verified_at' => now()]);
                $this->activities->record($user->getKey(), MemberActivityType::EmailVerified);
                $membership = $this->users->withProfile($user)->membership;

                if ($membership) {
                    SendApplicationReceivedEmail::dispatch($membership->getKey())->afterCommit();
                }
            }

            return $user;
        });

        return [
            'next_step' => 'create_password',
            'password_token' => $this->passwords->issueToken($user, config('apfspn.auth.password_setup_ttl_minutes'), PasswordResetService::PURPOSE_SETUP),
        ];
    }

    public function createPassword(string $passwordToken, string $password, AuthClient $client): array
    {
        $user = $this->passwords->reset($passwordToken, $password, $this->users, PasswordResetService::PURPOSE_SETUP);
        $this->tokens->assertCanAuthenticate($user);
        $this->users->update($user, ['last_login_at' => now()]);

        return [
            'next_step' => 'authenticated',
            'tokens' => $this->tokens->issue($user, $client, TokenAudience::Member),
            'member' => $this->users->withProfile($user),
        ];
    }

    public function resendVerification(string $email): array
    {
        $user = $this->users->findByEmail($email);
        $subject = $user && ! $user->hasVerifiedEmail() ? $user : null;

        return $this->challenges->create($subject, User::class, ChallengePurpose::EmailVerification, $email);
    }

    public function reissueVerificationCode(string $email): ?array
    {
        $user = $this->users->findByEmail($email);

        if (! $user || $user->hasVerifiedEmail()) {
            return null;
        }

        return $this->challenges->reissueWithoutSending($user, ChallengePurpose::EmailVerification, $user->email);
    }

    private function document(MemberType $type, array $data): mixed
    {
        return $type === MemberType::Corporate ? $data['registration_document'] : $data['letter_of_authorisation'];
    }

    private function organisationAttributes(MemberType $type, array $data): array
    {
        $corporate = $type === MemberType::Corporate;

        return [
            'type' => $type,
            'name' => $corporate ? $data['company_name'] : $data['institution_name'],
            'registration_number' => $data['registration_number'],
            'year_established' => $corporate ? ($data['year_established'] ?? null) : null,
            'business_type' => $corporate ? $data['business_type'] : null,
            'institution_type' => $corporate ? null : $data['institution_type'],
            'department' => $corporate ? null : ($data['department'] ?? null),
            'address' => $data['address'],
            'state_code' => $data['state_code'],
            'city' => $data['city'] ?? null,
            'website' => $corporate ? ($data['website'] ?? null) : null,
        ];
    }
}
