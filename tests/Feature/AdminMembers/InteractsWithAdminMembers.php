<?php

namespace Tests\Feature\AdminMembers;

use App\Enums\MembershipStatus;
use App\Enums\MemberType;
use App\Models\Admin;
use App\Models\Membership;
use App\Models\OrganisationProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

trait InteractsWithAdminMembers
{
    private array $adminTokenCache = [];

    private int $issuedMemberIds = 0;

    protected function adminToken(string $role = 'super_admin'): string
    {
        if (isset($this->adminTokenCache[$role])) {
            return $this->adminTokenCache[$role];
        }

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = tap(Admin::factory()->create())->assignRole($role);

        return $this->adminTokenCache[$role] = $this->adminTokens($admin)['access_token'];
    }

    protected function pendingMember(array $user = [], array $membership = [], ?array $organisation = null): User
    {
        $member = User::factory()->has(Membership::factory()->pending()->state($membership + [
            'state_code' => $user['state_code'] ?? $organisation['state_code'] ?? 'LA',
            'member_type' => $organisation['type'] ?? MemberType::Individual,
        ]))->create($user);

        if ($organisation !== null) {
            OrganisationProfile::create($organisation + [
                'user_uuid' => $member->uuid,
                'name' => 'Chow Co Limited',
                'registration_number' => 'RC123456',
                'business_type' => 'Catering',
                'address' => '12 Allen Avenue, Ikeja',
                'state_code' => 'LA',
                'city' => 'Ikeja',
            ]);
        }

        return $member->load(['membership', 'organisationProfile']);
    }

    protected function corporateMember(array $user = [], array $organisation = [], array $membership = []): User
    {
        return $this->pendingMember(['state_code' => null, 'city' => null] + $user, $membership, $organisation + [
            'type' => MemberType::Corporate,
            'document_public_id' => 'members/documents/cac.pdf',
        ]);
    }

    protected function activeMember(array $user = [], array $membership = []): User
    {
        return $this->pendingMember($user, $membership + [
            'status' => MembershipStatus::Active,
            'member_id' => 'APFSPNLA'.(900 + ++$this->issuedMemberIds),
            'approved_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    protected function memberUrl(User $member, string $suffix = ''): string
    {
        return '/api/v1/admin/members/'.$member->uuid.($suffix !== '' ? '/'.$suffix : '');
    }
}
