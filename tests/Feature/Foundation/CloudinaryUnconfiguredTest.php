<?php

namespace Tests\Feature\Foundation;

use App\Enums\MembershipStatus;
use App\Enums\MemberType;
use App\Helpers\Uploads\PrivateFileStore;
use App\Jobs\DeletePrivateFile;
use App\Models\Membership;
use App\Models\OrganisationProfile;
use App\Models\User;
use Cloudinary\Cloudinary;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class CloudinaryUnconfiguredTest extends TestCase
{
    use InteractsWithAuth, RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.cloudinary' => ['driver' => 'cloudinary', 'secure' => true, 'throw' => true],
            'apfspn.uploads.cloud_name' => null,
        ]);
        $this->app->forgetInstance(Cloudinary::class);
        $this->app->forgetInstance(PrivateFileStore::class);
        Storage::forgetDisk('cloudinary');
    }

    public function test_member_pages_return_a_null_document_url(): void
    {
        $member = $this->corporateMember();
        $adminToken = $this->adminTokens($this->superAdmin())['access_token'];

        $this->api('GET', '/api/v1/admin/members/pending', [], $adminToken)
            ->assertOk()
            ->assertJsonPath('data.items.0.uuid', $member->uuid)
            ->assertJsonPath('data.items.0.organisation.document_url', null)
            ->assertJsonPath('data.items.0.organisation.document_url_expires_at', null);

        $this->api('GET', '/api/v1/admin/members/'.$member->uuid, [], $adminToken)
            ->assertOk()
            ->assertJsonPath('data.organisation.document_url', null)
            ->assertJsonPath('data.organisation.document_url_expires_at', null);

        $memberToken = $this->api('POST', '/api/v1/member/auth/login', ['email' => $member->email, 'password' => UserFactory::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'authenticated')
            ->assertJsonPath('data.member.organisation.document_url', null)
            ->json('data.tokens.access_token');

        $this->api('GET', '/api/v1/member/profile', [], $memberToken)
            ->assertOk()
            ->assertJsonPath('data.organisation.name', 'Chow Co Limited')
            ->assertJsonPath('data.organisation.document_url', null);
    }

    public function test_uploads_are_refused_with_a_clean_error_and_a_warning(): void
    {
        Log::spy();
        $member = $this->corporateMember();
        $token = $this->memberTokens($member)['access_token'];

        $this->api('PUT', '/api/v1/member/profile', ['photo' => self::PNG], $token)
            ->assertStatus(503)
            ->assertExactJson([
                'error' => true,
                'message' => 'File uploads are not available right now.',
                'data' => ['code' => 'uploads_unavailable'],
            ]);

        Log::shouldHaveReceived('warning')->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_deleting_a_private_file_is_skipped_with_a_warning(): void
    {
        Log::spy();

        (new DeletePrivateFile('members/documents/demo.pdf'))->handle();

        Log::shouldHaveReceived('warning')->once();
    }

    private function corporateMember(): User
    {
        $member = User::factory()
            ->withoutTwoFactor()
            ->has(Membership::factory()->pending()->state(['member_type' => MemberType::Corporate]))
            ->create(['state_code' => null, 'city' => null]);

        OrganisationProfile::create([
            'user_uuid' => $member->uuid,
            'type' => MemberType::Corporate,
            'name' => 'Chow Co Limited',
            'registration_number' => 'RC123456',
            'business_type' => 'Catering',
            'address' => '12 Allen Avenue, Ikeja',
            'state_code' => 'LA',
            'document_public_id' => 'members/documents/cac.pdf',
        ]);

        $this->assertSame(MembershipStatus::PendingApproval, $member->membership->status);

        return $member;
    }
}
