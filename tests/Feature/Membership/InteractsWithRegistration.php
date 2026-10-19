<?php

namespace Tests\Feature\Membership;

use App\Helpers\Uploads\PrivateFileStore;
use App\Jobs\SendOtpEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakePrivateFileStore;

trait InteractsWithRegistration
{
    protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    protected FakePrivateFileStore $privateFiles;

    protected function fakeUploads(): void
    {
        Storage::fake('cloudinary');
        config(['apfspn.uploads.cloud_name' => 'apfspn-test']);

        $this->privateFiles = new FakePrivateFileStore;
        $this->app->instance(PrivateFileStore::class, $this->privateFiles);
    }

    protected function multipart(string $uri, array $data, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        $headers = ['Accept' => 'application/json'] + ($token ? ['Authorization' => 'Bearer '.$token] : []);

        return $this->post($uri, $data, $headers);
    }

    protected function pdfUpload(string $name = 'document.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::PDF);
    }

    protected function individualPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Obi',
            'email' => 'ada@apfspn.test',
            'phone' => '08031234567',
            'bio' => 'Executive chef with ten years in hotel kitchens.',
            'photo' => 'data:image/png;base64,'.self::PNG,
            'state_code' => 'LA',
            'city' => 'Ikeja',
            'consent' => true,
        ], $overrides);
    }

    protected function corporatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Tunde Bello',
            'email' => 'tunde@apfspn.test',
            'phone' => '08039876543',
            'company_name' => 'Chow Co Limited',
            'registration_number' => 'RC123456',
            'year_established' => 2015,
            'business_type' => 'Catering',
            'address' => '12 Allen Avenue, Ikeja',
            'state_code' => 'LA',
            'city' => 'Ikeja',
            'website' => 'https://chowco.ng',
            'registration_document' => $this->pdfUpload('cac.pdf'),
            'consent' => '1',
        ], $overrides);
    }

    protected function institutionalPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ngozi Eze',
            'email' => 'ngozi@apfspn.test',
            'phone' => '08035551234',
            'institution_name' => 'Benin Culinary Institute',
            'institution_type' => 'Training institution',
            'registration_number' => 'EDU-2020-114',
            'department' => 'School of Hospitality',
            'address' => '4 Airport Road, Benin City',
            'state_code' => 'ED',
            'city' => 'Benin City',
            'letter_of_authorisation' => $this->pdfUpload('letter.pdf'),
            'consent' => '1',
        ], $overrides);
    }

    protected function payloadFor(string $type, array $overrides = []): array
    {
        return $this->{$type.'Payload'}($overrides);
    }

    protected function register(string $type, array $overrides = []): TestResponse
    {
        return $this->multipart('/api/v1/member/register/'.$type, $this->payloadFor($type, $overrides));
    }

    protected function verifyEmailFor(TestResponse $registration): TestResponse
    {
        return $this->api('POST', '/api/v1/member/register/verify-email', [
            'challenge_token' => $registration->json('data.challenge.challenge_token'),
            'code' => Queue::pushed(SendOtpEmail::class)->last()->code,
        ]);
    }

    protected function completeRegistration(string $type, array $overrides = []): TestResponse
    {
        $passwordToken = $this->verifyEmailFor($this->register($type, $overrides)->assertCreated())
            ->assertOk()
            ->json('data.password_token');

        return $this->api('POST', '/api/v1/member/register/create-password', [
            'password_token' => $passwordToken,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);
    }
}
