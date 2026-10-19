<?php

namespace App\Services\Membership;

use App\Enums\MemberActivityType;
use App\Enums\MembershipNotice;
use App\Enums\MembershipStatus;
use App\Exceptions\ApiException;
use App\Jobs\SendMembershipStatusEmail;
use App\Models\Membership;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\TokenService;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class MemberApprovalService
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly MembershipRepositoryInterface $memberships,
        private readonly MemberIdGenerator $memberIds,
        private readonly AuditLogService $audit,
        private readonly TokenService $tokens,
        private readonly MemberActivityService $activities,
    ) {}

    public function approve(string $memberUuid): Membership
    {
        return $this->transition($memberUuid, function (Membership $membership) {
            if ($membership->status === MembershipStatus::Active && $membership->member_id !== null) {
                return null;
            }

            $this->assertCan($membership, $membership->status->canBeApproved());

            if ($membership->user->email_verified_at === null) {
                throw new ApiException('The member has not verified their email address yet.', 422, ['code' => 'email_not_verified']);
            }

            $approvedAt = now();

            $membership->fill([
                'member_id' => $membership->member_id ?? $this->memberIds->next($membership->state_code),
                'status' => MembershipStatus::Active,
                'approved_at' => $approvedAt,
                'approved_by_admin_uuid' => $this->audit->currentAdminUuid(),
                'expires_at' => $approvedAt->copy()->addYear(),
            ]);

            return MembershipNotice::Approved;
        });
    }

    public function reject(string $memberUuid, string $reason): Membership
    {
        return $this->transition($memberUuid, function (Membership $membership) use ($reason) {
            $this->assertCan($membership, $membership->status->canBeRejected());

            $membership->fill([
                'status' => MembershipStatus::Rejected,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
                'rejected_by_admin_uuid' => $this->audit->currentAdminUuid(),
            ]);

            return MembershipNotice::Rejected;
        });
    }

    public function suspend(string $memberUuid, string $reason): Membership
    {
        return $this->transition($memberUuid, function (Membership $membership) use ($reason) {
            $this->assertCan($membership, $membership->status->canBeSuspended());

            $membership->fill([
                'status' => MembershipStatus::Suspended,
                'suspension_reason' => $reason,
                'suspended_at' => now(),
                'suspended_by_admin_uuid' => $this->audit->currentAdminUuid(),
            ]);

            $this->tokens->revokeAll($membership->user);

            return MembershipNotice::Suspended;
        });
    }

    public function reactivate(string $memberUuid): Membership
    {
        return $this->transition($memberUuid, function (Membership $membership) {
            $this->assertCan($membership, $membership->status->canBeReactivated());

            $membership->fill([
                'status' => $membership->expires_at?->isPast() ? MembershipStatus::Expired : MembershipStatus::Active,
                'reactivated_at' => now(),
            ]);

            return MembershipNotice::Reactivated;
        });
    }

    private function transition(string $memberUuid, Closure $apply): Membership
    {
        return DB::transaction(function () use ($memberUuid, $apply) {
            $membership = $this->memberships->findByUserForUpdate($memberUuid)
                ?? throw (new ModelNotFoundException)->setModel(Membership::class, [$memberUuid]);

            $notice = $apply($membership);

            if ($notice === null) {
                return $membership;
            }

            $changes = $this->audit->changeSet($membership);
            $this->memberships->save($membership);
            $this->activities->record($membership->user_uuid, MemberActivityType::forMembershipNotice($notice), $membership);
            $this->audit->record('membership.'.$notice->value, $membership, $changes);

            SendMembershipStatusEmail::dispatch(
                $membership->getKey(),
                $notice,
                (string) $membership->updated_at->getTimestamp(),
            )->afterCommit();

            return $membership;
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function assertCan(Membership $membership, bool $allowed): void
    {
        if (! $allowed) {
            throw new ApiException(
                'This action is not allowed while the membership is '.strtolower($membership->status->label()).'.',
                422,
                ['code' => 'invalid_status_transition', 'current_status' => $membership->status->value],
            );
        }
    }
}
