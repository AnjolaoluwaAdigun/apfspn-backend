<?php

namespace Tests\Feature\Membership;

use App\Enums\MemberType;
use App\Http\Resources\OrganisationProfileResource;
use App\Models\Admin;
use App\Models\Membership;
use App\Models\OrganisationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use InteractsWithAuth, InteractsWithRegistration, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->fakeUploads();
    }

    public function test_member_can_view_profile(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['bio' => 'Pastry chef']);

        $this->api('GET', '/api/v1/member/profile', [], $this->memberTokens($user)['access_token'])
            ->assertOk()
            ->assertJsonPath('data.uuid', $user->uuid)
            ->assertJsonPath('data.bio', 'Pastry chef')
            ->assertJsonPath('data.state', 'Lagos')
            ->assertJsonPath('data.directory_visible', false)
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonMissingPath('data.status')
            ->assertJsonPath('data.membership.status', 'active')
            ->assertJsonPath('data.organisation', null);
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->api('GET', '/api/v1/member/profile')->assertStatus(401);
    }

    public function test_member_can_update_profile_photo_and_directory_visibility(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $token = $this->memberTokens($user)['access_token'];

        $this->api('PUT', '/api/v1/member/profile', [
            'phone' => '08030000001',
            'bio' => 'Updated bio',
            'city' => 'Lekki',
            'directory_visible' => true,
            'photo' => 'data:image/png;base64,'.self::PNG,
        ], $token)
            ->assertOk()
            ->assertJsonPath('data.phone', '08030000001')
            ->assertJsonPath('data.bio', 'Updated bio')
            ->assertJsonPath('data.city', 'Lekki')
            ->assertJsonPath('data.directory_visible', true);

        $user->refresh();
        $this->assertNotNull($user->photo_url);
        $this->assertTrue($user->directory_visible);

        $photo = $user->photo_url;
        $this->api('PUT', '/api/v1/member/profile', ['bio' => 'Again'], $token)->assertOk()->assertJsonPath('data.photo_url', $photo);
    }

    public function test_photo_can_be_uploaded_as_multipart_with_method_spoofing(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $token = $this->memberTokens($user)['access_token'];

        $this->multipart('/api/v1/member/profile', [
            '_method' => 'PUT',
            'photo' => UploadedFile::fake()->createWithContent('me.png', base64_decode(self::PNG)),
        ], $token)->assertOk();

        $this->assertNotNull($user->refresh()->photo_url);
    }

    public function test_member_cannot_change_protected_profile_fields(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $this->api('PUT', '/api/v1/member/profile', [
            'email' => 'new@example.com',
            'name' => 'Someone Else',
            'member_id' => 'APFSPNLA001',
            'membership_status' => 'active',
            'state_code' => 'ED',
            'address' => '1 Somewhere Road',
        ], $this->memberTokens($user)['access_token'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'name', 'member_id', 'membership_status', 'state_code', 'address'], 'data');

        $this->assertSame($user->email, $user->refresh()->email);
    }

    public function test_phone_must_stay_unique(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->withoutTwoFactor()->create();

        $this->api('PUT', '/api/v1/member/profile', ['phone' => $other->phone], $this->memberTokens($user)['access_token'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone', 'data');
    }

    public function test_corporate_member_updates_organisation_and_sees_signed_document_url(): void
    {
        $token = $this->completeRegistration('corporate')->assertOk()->json('data.tokens.access_token');
        $publicId = OrganisationProfile::firstOrFail()->document_public_id;

        $response = $this->api('PUT', '/api/v1/member/profile', [
            'address' => '5 Awolowo Road, Ikoyi',
            'business_type' => 'Restaurant',
            'city' => 'Ikoyi',
        ], $token)
            ->assertOk()
            ->assertJsonPath('data.organisation.address', '5 Awolowo Road, Ikoyi')
            ->assertJsonPath('data.organisation.business_type', 'Restaurant')
            ->assertJsonPath('data.organisation.city', 'Ikoyi')
            ->assertJsonPath('data.organisation.name', 'Chow Co Limited')
            ->assertJsonMissingPath('data.organisation.document_public_id');

        $url = $response->json('data.organisation.document_url');
        $this->assertStringStartsWith('https://api.cloudinary.com/', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($publicId, $query['public_id']);
        $this->assertSame('authenticated', $query['type']);
        $this->assertEqualsWithDelta(now()->addMinutes(10)->getTimestamp(), (int) $query['expires_at'], 5);
        $this->assertNotNull($response->json('data.organisation.document_url_expires_at'));
    }

    public function test_corporate_member_cannot_set_institution_fields(): void
    {
        $token = $this->completeRegistration('corporate')->json('data.tokens.access_token');

        $this->api('PUT', '/api/v1/member/profile', ['department' => 'Kitchen'], $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors('department', 'data');
    }

    public function test_document_url_is_only_shown_to_owner_and_admins(): void
    {
        $owner = User::factory()->create();
        $organisation = OrganisationProfile::create([
            'user_uuid' => $owner->uuid,
            'type' => MemberType::Corporate,
            'name' => 'Chow Co Limited',
            'registration_number' => 'RC1',
            'address' => '12 Allen Avenue',
            'state_code' => 'LA',
            'document_public_id' => 'members/documents/cac.pdf',
        ]);

        $resolve = function ($viewer) use ($organisation) {
            $request = Request::create('/');
            $request->setUserResolver(fn () => $viewer);

            return (new OrganisationProfileResource($organisation))->toArray($request);
        };

        $this->assertStringContainsString('cac.pdf', $resolve($owner)['document_url']);
        $this->assertStringContainsString('cac.pdf', $resolve(Admin::factory()->create())['document_url']);
        $this->assertNull($resolve(User::factory()->create())['document_url']);
        $this->assertNull($resolve(null)['document_url']);
    }

    public function test_pending_member_sees_pending_status_on_profile(): void
    {
        $user = User::factory()->withoutTwoFactor()->pendingApproval()->create();

        $this->assertSame(1, Membership::count());

        $this->api('GET', '/api/v1/member/profile', [], $this->memberTokens($user)['access_token'])
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'pending_approval')
            ->assertJsonPath('data.membership.status_label', 'Pending Approval');
    }
}
