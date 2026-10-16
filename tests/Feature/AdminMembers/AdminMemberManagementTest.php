<?php

namespace Tests\Feature\AdminMembers;

use App\Enums\MembershipStatus;
use App\Jobs\DeletePrivateFile;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\Feature\Membership\InteractsWithRegistration;
use Tests\TestCase;

class AdminMemberManagementTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth, InteractsWithRegistration, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->fakeUploads();
    }

    public function test_overview_counts_memberships_by_status(): void
    {
        $this->pendingMember();
        $this->pendingMember();
        $this->activeMember();
        $this->activeMember([], ['status' => MembershipStatus::Suspended]);
        $this->pendingMember([], ['status' => MembershipStatus::Rejected]);
        $this->activeMember([], ['status' => MembershipStatus::Expired]);
        $this->pendingMember()->delete();

        $this->api('GET', '/api/v1/admin/members/overview', [], $this->adminToken())
            ->assertOk()
            ->assertExactJson([
                'error' => false,
                'message' => 'Request successful.',
                'data' => ['all' => 6, 'pending_approval' => 2, 'active' => 1, 'rejected' => 1, 'suspended' => 1, 'expired' => 1],
            ]);
    }

    public function test_list_is_paginated_inside_data(): void
    {
        foreach (range(1, 3) as $i) {
            $this->travel(1)->minutes();
            $this->pendingMember(['name' => 'Member '.$i]);
        }

        $this->api('GET', '/api/v1/admin/members?per_page=2&page=1', [], $this->adminToken())
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Member 3')
            ->assertJsonPath('data.items.0.membership.status', 'pending_approval')
            ->assertJsonPath('data.pagination', ['current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2])
            ->assertJsonMissingPath('meta');

        $this->api('GET', '/api/v1/admin/members?per_page=500', [], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page'], 'data');
    }

    public function test_search_matches_name_email_organisation_and_member_id(): void
    {
        $token = $this->adminToken();
        $byName = $this->pendingMember(['name' => 'Adaeze Okafor']);
        $byEmail = $this->pendingMember(['email' => 'kitchen.boss@example.com']);
        $byOrganisation = $this->corporateMember([], ['name' => 'Suya Palace Limited']);
        $byMemberId = $this->activeMember([], ['member_id' => 'APFSPNOY042']);

        foreach ([
            'okafor' => $byName,
            'KITCHEN.BOSS' => $byEmail,
            'suya palace' => $byOrganisation,
            'APFSPNOY042' => $byMemberId,
        ] as $term => $expected) {
            $this->api('GET', '/api/v1/admin/members?search='.urlencode($term), [], $token)
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath('data.items.0.uuid', $expected->uuid);
        }

        $this->api('GET', '/api/v1/admin/members?search=%25', [], $token)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_filters_by_member_type_status_and_date_joined(): void
    {
        $token = $this->adminToken();
        $this->travelTo(now()->setDate(2026, 1, 10));
        $january = $this->pendingMember();
        $this->travelTo(now()->setDate(2026, 3, 5));
        $march = $this->activeMember();
        $corporate = $this->corporateMember();
        $this->travelBack();

        $this->api('GET', '/api/v1/admin/members?member_type=corporate', [], $token)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.uuid', $corporate->uuid)
            ->assertJsonPath('data.items.0.organisation.name', 'Chow Co Limited');

        $this->api('GET', '/api/v1/admin/members?status=active', [], $token)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.uuid', $march->uuid);

        $this->api('GET', '/api/v1/admin/members?joined_from=2026-01-01&joined_to=2026-01-31', [], $token)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.uuid', $january->uuid);

        $this->api('GET', '/api/v1/admin/members?joined_from=2026-03-05&status=pending_approval&member_type=corporate', [], $token)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->api('GET', '/api/v1/admin/members?status=archived&joined_from=2026-02-01&joined_to=2026-01-01', [], $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'joined_to'], 'data');
    }

    public function test_pending_queue_lists_verified_pending_members_oldest_first(): void
    {
        $older = $this->pendingMember();
        $this->travel(1)->hours();
        $newer = $this->pendingMember();
        $this->pendingMember(['email_verified_at' => null]);
        $this->activeMember();

        $this->api('GET', '/api/v1/admin/members/pending', [], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.uuid', $older->uuid)
            ->assertJsonPath('data.items.1.uuid', $newer->uuid);
    }

    public function test_show_returns_profile_organisation_membership_and_signed_document_url(): void
    {
        $member = $this->corporateMember(['name' => 'Tunde Bello']);

        $this->api('GET', $this->memberUrl($member), [], $this->adminToken('membership_coordinator'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Tunde Bello')
            ->assertJsonPath('data.membership.status', 'pending_approval')
            ->assertJsonPath('data.organisation.registration_number', 'RC123456')
            ->assertJsonPath('data.organisation.document_url', fn (string $url) => str_contains($url, 'public_id=members%2Fdocuments%2Fcac.pdf') && str_contains($url, 'expires_at='))
            ->assertJsonPath('data.organisation.document_url_expires_at', fn (?string $value) => $value !== null)
            ->assertJsonMissingPath('data.organisation.document_public_id');
    }

    public function test_signed_document_url_is_not_available_to_members_through_admin_routes(): void
    {
        $member = $this->corporateMember(['two_factor_enabled' => false]);
        $other = $this->pendingMember(['two_factor_enabled' => false]);

        $this->api('GET', $this->memberUrl($member), [], $this->memberTokens($other)['access_token'])
            ->assertForbidden()
            ->assertJsonPath('data.code', 'wrong_audience');

        $this->api('GET', $this->memberUrl($member), [])->assertUnauthorized();
    }

    public function test_show_returns_404_for_unknown_or_deleted_members(): void
    {
        $token = $this->adminToken();
        $deleted = $this->pendingMember();
        $deleted->delete();

        $this->api('GET', '/api/v1/admin/members/'.fake()->uuid(), [], $token)->assertNotFound();
        $this->api('GET', $this->memberUrl($deleted), [], $token)->assertNotFound();
        $this->api('GET', '/api/v1/admin/members/not-a-uuid', [], $token)->assertNotFound();
    }

    public function test_admin_can_edit_member_details_with_an_audit_change_set(): void
    {
        $member = $this->corporateMember(['name' => 'Old Name', 'phone' => '08030000000']);

        $this->api('PUT', $this->memberUrl($member), [
            'name' => 'New Name',
            'phone' => '08031111111',
            'organisation' => ['name' => 'Chow Co Nigeria Limited', 'state_code' => 'ed', 'city' => 'Benin City'],
        ], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('message', 'Member updated.')
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.phone', '08031111111')
            ->assertJsonPath('data.organisation.name', 'Chow Co Nigeria Limited')
            ->assertJsonPath('data.organisation.state_code', 'ED');

        $audit = AuditLog::where('action', 'member.updated')->sole();
        $this->assertSame($member->uuid, $audit->auditable_uuid);
        $this->assertEqualsCanonicalizing(['name', 'phone', 'organisation.name', 'organisation.state_code', 'organisation.city'], $audit->changes['changed_fields']);
        $this->assertSame('Old Name', $audit->changes['previous']['name']);
        $this->assertSame('sha256:'.hash('sha256', '08030000000'), $audit->changes['previous']['phone']);
        $this->assertSame('Chow Co Limited', $audit->changes['previous']['organisation.name']);
        $this->assertSame('sha256:'.hash('sha256', '08031111111'), $audit->request_body['phone']);
        $this->assertSame('LA', $member->membership->refresh()->state_code);
    }

    public function test_no_op_edit_is_not_audited(): void
    {
        $member = $this->pendingMember(['name' => 'Same Name']);

        $this->api('PUT', $this->memberUrl($member), ['name' => 'Same Name'], $this->adminToken())->assertOk();

        $this->assertSame(0, AuditLog::where('action', 'member.updated')->count());
    }

    public function test_edit_rejects_email_and_protected_fields(): void
    {
        $member = $this->pendingMember();
        $taken = $this->pendingMember();

        $this->api('PUT', $this->memberUrl($member), [
            'email' => 'new@example.com',
            'status' => 'active',
            'member_id' => 'APFSPNLA123',
            'member_type' => 'corporate',
            'expires_at' => '2030-01-01',
            'phone' => $taken->phone,
            'organisation' => ['name' => 'Not an organisation'],
        ], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'status', 'member_id', 'member_type', 'expires_at', 'phone', 'organisation'], 'data');

        $this->assertNotSame('new@example.com', $member->refresh()->email);
    }

    public function test_edit_enforces_organisation_type_specific_fields(): void
    {
        $corporate = $this->corporateMember();

        $this->api('PUT', $this->memberUrl($corporate), [
            'state_code' => 'LA',
            'organisation' => ['department' => 'Kitchens', 'institution_type' => 'School', 'unknown' => 'x'],
        ], $this->adminToken())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['state_code', 'organisation', 'organisation.department', 'organisation.institution_type'], 'data');
    }

    public function test_delete_is_blocked_once_a_member_id_has_been_issued(): void
    {
        $token = $this->adminToken();
        $active = $this->activeMember();
        $suspended = $this->activeMember([], ['status' => MembershipStatus::Suspended]);

        $this->api('DELETE', $this->memberUrl($active), [], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'blocked')
            ->assertJsonPath('data.counts', ['issued_member_ids' => 1, 'active_memberships' => 1]);

        $this->api('DELETE', $this->memberUrl($suspended), [], $token)
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'blocked')
            ->assertJsonPath('data.counts', ['issued_member_ids' => 1, 'active_memberships' => 0]);

        $this->assertNotSoftDeleted($active);
        $this->assertNotSoftDeleted($suspended);
    }

    public function test_deleting_an_applicant_tombstones_the_account_and_frees_email_and_phone(): void
    {
        $member = $this->corporateMember(['email' => 'tunde@chowco.ng', 'phone' => '08039876543', 'two_factor_enabled' => false]);
        $memberToken = $this->memberTokens($member)['access_token'];

        $this->api('DELETE', $this->memberUrl($member), [], $this->adminToken())
            ->assertOk()
            ->assertJsonPath('message', 'Member deleted.')
            ->assertJsonPath('data', null);

        $deleted = User::withTrashed()->find($member->uuid);
        $this->assertSoftDeleted($deleted);
        $this->assertSame('deleted+'.$member->uuid.'@deleted.local', $deleted->email);
        $this->assertNull($deleted->phone);
        $this->assertSame(hash('sha256', 'tunde@chowco.ng'), $deleted->deleted_email_hash);
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $member->uuid)->count());
        $this->assertSame(1, AuditLog::where('action', 'member.deleted')->where('auditable_uuid', $member->uuid)->count());
        Queue::assertPushed(DeletePrivateFile::class, fn (DeletePrivateFile $job) => $job->publicId === 'members/documents/cac.pdf');

        $this->api('GET', '/api/v1/member/profile', [], $memberToken)->assertUnauthorized();

        $this->register('corporate', ['email' => 'tunde@chowco.ng', 'phone' => '08039876543'])->assertCreated();
    }

    public function test_deleting_a_rejected_applicant_is_allowed(): void
    {
        $member = $this->pendingMember([], ['status' => MembershipStatus::Rejected]);

        $this->api('DELETE', $this->memberUrl($member), [], $this->adminToken())->assertOk();

        $this->assertSoftDeleted($member);
        Queue::assertNotPushed(DeletePrivateFile::class);
    }

    public function test_private_file_job_deletes_the_document(): void
    {
        $this->privateFiles->put('members/documents/cac.pdf', 'pdf');

        (new DeletePrivateFile('members/documents/cac.pdf'))->handle();

        $this->assertArrayNotHasKey('members/documents/cac.pdf', $this->privateFiles->files);
    }

    public function test_export_streams_the_filtered_list_as_csv(): void
    {
        $active = $this->activeMember(['name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '+2348031234567'], ['member_id' => 'APFSPNLA001']);
        $this->pendingMember(['name' => 'Pending Person']);
        $this->activeMember(['name' => '=HYPERLINK("http://evil")']);

        $response = $this->api('GET', '/api/v1/admin/members/export?status=active', [], $this->adminToken('membership_coordinator'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('attachment; filename=apfspn-members-', $response->headers->get('Content-Disposition'));

        $rows = array_map(str_getcsv(...), array_filter(explode("\n", $response->streamedContent())));

        $this->assertSame(['member_id', 'name', 'email', 'phone', 'member_type', 'status', 'organisation', 'state', 'city', 'joined_at', 'approved_at', 'expires_at'], $rows[0]);
        $this->assertCount(3, $rows);
        $names = array_column($rows, 1);
        $this->assertContains('Ada Obi', $names);
        $this->assertContains("'=HYPERLINK(\"http://evil\")", $names);
        $this->assertNotContains('Pending Person', $names);

        $ada = collect($rows)->firstWhere(1, 'Ada Obi');
        $this->assertSame(['APFSPNLA001', 'Ada Obi', 'ada@example.com', "'+2348031234567", 'individual', 'active', '', 'Lagos', 'Ikeja'], array_slice($ada, 0, 9));
        $this->assertSame($active->membership->expires_at->toIso8601String(), $ada[11]);

        $this->assertSame(1, AuditLog::where('action', 'member.exported')->count());
        $this->assertSame('active', AuditLog::where('action', 'member.exported')->value('request_body')['status']);
    }

    public function test_export_requires_the_export_permission(): void
    {
        $this->api('GET', '/api/v1/admin/members/export', [], $this->adminToken('association_manager'))
            ->assertForbidden()
            ->assertJsonPath('error', true)
            ->assertJsonPath('message', 'You are not allowed to perform this action.');

        $this->assertSame(0, AuditLog::where('action', 'member.exported')->count());
    }

    public static function permissionMatrix(): array
    {
        return [
            'association manager can view' => ['association_manager', 'GET', '', 200],
            'association manager cannot update' => ['association_manager', 'PUT', '', 403],
            'association manager cannot approve' => ['association_manager', 'POST', 'approve', 403],
            'association manager cannot reject' => ['association_manager', 'POST', 'reject', 403],
            'association manager cannot suspend' => ['association_manager', 'POST', 'suspend', 403],
            'association manager cannot reactivate' => ['association_manager', 'POST', 'reactivate', 403],
            'association manager cannot delete' => ['association_manager', 'DELETE', '', 403],
            'membership coordinator can update' => ['membership_coordinator', 'PUT', '', 200],
            'membership coordinator can approve' => ['membership_coordinator', 'POST', 'approve', 200],
            'membership coordinator cannot delete' => ['membership_coordinator', 'DELETE', '', 403],
            'editor cannot view' => ['editor', 'GET', '', 403],
            'author cannot view' => ['author', 'GET', '', 403],
            'events coordinator cannot view' => ['events_coordinator', 'GET', '', 403],
            'events coordinator cannot approve' => ['events_coordinator', 'POST', 'approve', 403],
            'super admin can delete' => ['super_admin', 'DELETE', '', 200],
        ];
    }

    #[DataProvider('permissionMatrix')]
    public function test_each_route_enforces_its_permission(string $role, string $method, string $suffix, int $status): void
    {
        $member = $this->pendingMember();
        $body = in_array($suffix, ['reject', 'suspend'], true) ? ['reason' => 'Testing permissions.'] : ['name' => 'Renamed'];

        $response = $this->api($method, $this->memberUrl($member, $suffix), $body, $this->adminToken($role));

        if ($status === 403) {
            $response->assertForbidden()->assertJsonPath('error', true);
            $this->assertSame(MembershipStatus::PendingApproval, $member->membership->refresh()->status);

            return;
        }

        $response->assertStatus($status);
    }

    public static function listRoutes(): array
    {
        return [
            'overview' => ['/api/v1/admin/members/overview'],
            'list' => ['/api/v1/admin/members'],
            'pending' => ['/api/v1/admin/members/pending'],
        ];
    }

    #[DataProvider('listRoutes')]
    public function test_list_routes_need_members_view(string $uri): void
    {
        $this->api('GET', $uri, [], $this->adminToken('association_manager'))->assertOk();
        $this->api('GET', $uri, [], $this->adminToken('editor'))->assertForbidden();
    }
}
