<?php

namespace App\Services\Membership;

use App\Enums\MemberActivityType;
use App\Enums\MembershipStatus;
use App\Exceptions\ApiException;
use App\Jobs\DeletePrivateFile;
use App\Models\User;
use App\Repositories\Contracts\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use App\Repositories\Contracts\OrganisationProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\TokenService;
use App\Support\CsvSanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminMemberService
{
    public const DEFAULT_PER_PAGE = 15;

    private const USER_FIELDS = ['name', 'phone', 'bio', 'state_code', 'city', 'directory_visible'];

    private const ORGANISATION_FIELDS = [
        'name', 'registration_number', 'year_established', 'business_type', 'institution_type',
        'department', 'address', 'state_code', 'city', 'website',
    ];

    private const DELETABLE_STATUSES = [MembershipStatus::PendingApproval, MembershipStatus::Rejected];

    private const EXPORT_COLUMNS = [
        'member_id', 'name', 'email', 'phone', 'member_type', 'status', 'organisation', 'state',
        'city', 'joined_at', 'approved_at', 'expires_at',
    ];

    public function __construct(
        private readonly MemberRepositoryInterface $members,
        private readonly MembershipRepositoryInterface $memberships,
        private readonly UserRepositoryInterface $users,
        private readonly OrganisationProfileRepositoryInterface $organisations,
        private readonly EventRegistrationRepositoryInterface $eventRegistrations,
        private readonly TokenService $tokens,
        private readonly AuditLogService $audit,
        private readonly MemberActivityService $activities,
    ) {}

    public function overview(): array
    {
        $counts = $this->memberships->countsByStatus();
        $byStatus = array_map(fn (string $status) => $counts[$status] ?? 0, array_combine(MembershipStatus::values(), MembershipStatus::values()));

        return ['all' => array_sum($byStatus)] + $byStatus;
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->members->paginate($filters, $this->perPage($filters));
    }

    public function pending(array $filters): LengthAwarePaginator
    {
        return $this->members->paginatePending($filters, $this->perPage($filters));
    }

    public function show(string $uuid): User
    {
        return $this->members->findWithProfile($uuid);
    }

    public function update(string $uuid, array $data): User
    {
        DB::transaction(function () use ($uuid, $data) {
            $user = $this->lockedUser($uuid);
            $organisation = $this->organisations->findForUserForUpdate($user->getKey());

            $user->fill(Arr::only($data, self::USER_FIELDS));
            $changes = $this->audit->changeSet($user);

            if ($organisation && isset($data['organisation'])) {
                $organisation->fill(Arr::only($data['organisation'], self::ORGANISATION_FIELDS));
                $organisationChanges = $this->audit->changeSet($organisation);

                $changes['changed_fields'] = [...$changes['changed_fields'], ...array_map(fn (string $field) => 'organisation.'.$field, $organisationChanges['changed_fields'])];
                $changes['previous'] += Arr::prependKeysWith($organisationChanges['previous'], 'organisation.');

                $this->organisations->update($organisation, []);
            }

            $this->users->update($user, []);
            $this->audit->record('member.updated', $user, $changes);

            if ($changes['changed_fields'] !== []) {
                $this->activities->record($user->getKey(), MemberActivityType::ProfileUpdatedByAdmin, changedFields: $changes['changed_fields']);
            }
        });

        return $this->members->findWithProfile($uuid);
    }

    public function delete(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $user = $this->lockedUser($uuid);
            $membership = $this->memberships->findByUserForUpdate($user->getKey());

            $approved = $membership && ($membership->member_id !== null || ! in_array($membership->status, self::DELETABLE_STATUSES, true));
            $eventRegistrations = $this->eventRegistrations->countActiveForUser($user->getKey());

            if ($approved || $eventRegistrations > 0) {
                throw ApiException::blocked(
                    $approved
                        ? 'This member has been approved and cannot be deleted. Suspend the membership instead.'
                        : 'This member has event registrations and cannot be deleted.',
                    [
                        'issued_member_ids' => $membership?->member_id !== null ? 1 : 0,
                        'active_memberships' => $membership?->status === MembershipStatus::Active ? 1 : 0,
                        'event_registrations' => $eventRegistrations,
                    ],
                );
            }

            $documentId = $this->organisations->findForUserForUpdate($user->getKey())?->document_public_id;

            $this->tokens->revokeAll($user);
            $this->audit->record('member.deleted', $user);

            $this->users->tombstone($user, [
                'email' => 'deleted+'.$user->getKey().'@deleted.local',
                'phone' => null,
                'deleted_email_hash' => hash('sha256', Str::lower($user->email)),
            ]);

            if ($documentId) {
                DeletePrivateFile::dispatch($documentId)->afterCommit();
            }
        });
    }

    public function export(array $filters): iterable
    {
        $this->audit->record('member.exported');

        return $this->exportRows($filters);
    }

    private function exportRows(array $filters): iterable
    {
        yield self::EXPORT_COLUMNS;

        foreach ($this->members->lazyForExport($filters) as $user) {
            $membership = $user->membership;
            $organisation = $user->organisationProfile;
            $stateCode = $organisation?->state_code ?? $user->state_code;

            yield CsvSanitizer::row([
                $membership?->member_id,
                $user->name,
                $user->email,
                $user->phone,
                $membership?->member_type->value,
                $membership?->status->value,
                $organisation?->name,
                $stateCode ? config('apfspn.states.'.$stateCode) : null,
                $organisation?->city ?? $user->city,
                $membership?->created_at?->toIso8601String(),
                $membership?->approved_at?->toIso8601String(),
                $membership?->expires_at?->toIso8601String(),
            ]);
        }
    }

    private function lockedUser(string $uuid): User
    {
        return $this->users->findForUpdate($uuid)
            ?? throw (new ModelNotFoundException)->setModel(User::class, [$uuid]);
    }

    private function perPage(array $filters): int
    {
        return (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
    }
}
