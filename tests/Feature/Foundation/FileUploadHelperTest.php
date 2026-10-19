<?php

namespace Tests\Feature\Foundation;

use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\Uploads\CloudinaryPrivateFileStore;
use App\Helpers\Uploads\PrivateFileStore;
use Cloudinary\Cloudinary;
use Cloudinary\Exception\ConfigurationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakePrivateFileStore;
use Tests\TestCase;

class FileUploadHelperTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('cloudinary');
        config(['apfspn.uploads.cloud_name' => 'apfspn-test']);
    }

    public function test_url_on_our_cloudinary_domain_is_kept(): void
    {
        $url = 'https://res.cloudinary.com/apfspn-test/image/upload/v1/events/banner.jpg';

        $this->assertSame($url, FileUploadHelper::upload($url, 'events'));
    }

    public function test_foreign_url_is_rejected(): void
    {
        foreach ([
            'https://example.com/banner.jpg',
            'https://res.cloudinary.com/someone-else/image/upload/banner.jpg',
            'http://res.cloudinary.com/apfspn-test/image/upload/banner.jpg',
        ] as $url) {
            try {
                FileUploadHelper::upload($url, 'events');
                $this->fail("Expected {$url} to be rejected.");
            } catch (ApiException $e) {
                $this->assertSame('invalid_file_url', $e->payload()['code']);
            }
        }
    }

    public function test_base64_data_uri_is_stored(): void
    {
        $url = FileUploadHelper::upload('data:image/png;base64,'.self::PNG, 'avatars');

        $files = Storage::disk('cloudinary')->files('avatars');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', $files[0]);
        $this->assertNotNull($url);
    }

    public function test_uploaded_file_is_stored(): void
    {
        FileUploadHelper::upload(UploadedFile::fake()->create('letter.pdf', 20, 'application/pdf'), 'documents');

        $this->assertCount(1, Storage::disk('cloudinary')->files('documents'));
    }

    public function test_invalid_base64_is_rejected(): void
    {
        try {
            FileUploadHelper::upload('not base64 at all!', 'avatars');
            $this->fail('Expected invalid base64 to be rejected.');
        } catch (ApiException $e) {
            $this->assertSame('invalid_file', $e->payload()['code']);
        }
    }

    public function test_null_keeps_the_current_url(): void
    {
        $this->assertSame('https://res.cloudinary.com/apfspn-test/x.jpg', FileUploadHelper::upload(null, 'events', 'https://res.cloudinary.com/apfspn-test/x.jpg'));
    }

    public function test_private_uploaded_file_goes_to_private_store_and_returns_public_id(): void
    {
        $store = $this->fakePrivateStore();

        $publicId = FileUploadHelper::upload(UploadedFile::fake()->createWithContent('cac.pdf', '%PDF-1.4 test'), 'documents', private: true);

        $this->assertMatchesRegularExpression('#^documents/[0-9a-f-]{36}\.pdf$#', $publicId);
        $this->assertSame('%PDF-1.4 test', $store->files[$publicId]);
        $this->assertSame([], Storage::disk('cloudinary')->allFiles());
    }

    public function test_private_base64_goes_to_private_store(): void
    {
        $store = $this->fakePrivateStore();

        $publicId = FileUploadHelper::upload('data:image/png;base64,'.self::PNG, 'documents', private: true);

        $this->assertStringEndsWith('.png', $publicId);
        $this->assertArrayHasKey($publicId, $store->files);
    }

    public function test_private_upload_rejects_any_url(): void
    {
        $this->fakePrivateStore();

        foreach (['https://res.cloudinary.com/apfspn-test/raw/upload/cac.pdf', 'https://example.com/cac.pdf'] as $url) {
            try {
                FileUploadHelper::upload($url, 'documents', private: true);
                $this->fail("Expected {$url} to be rejected.");
            } catch (ApiException $e) {
                $this->assertSame('invalid_file_url', $e->payload()['code']);
            }
        }
    }

    public function test_private_null_keeps_the_current_public_id(): void
    {
        $this->assertSame('documents/cac.pdf', FileUploadHelper::upload(null, 'documents', 'documents/cac.pdf', private: true));
    }

    public function test_temporary_url_is_signed_and_short_lived(): void
    {
        $this->fakePrivateStore();

        $url = FileUploadHelper::temporaryUrl('documents/cac.pdf', 5);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('documents/cac.pdf', $query['public_id']);
        $this->assertSame('authenticated', $query['type']);
        $this->assertEqualsWithDelta(now()->addMinutes(5)->getTimestamp(), (int) $query['expires_at'], 2);
        $this->assertNull(FileUploadHelper::temporaryUrl(null));
    }

    public function test_temporary_url_is_null_when_cloudinary_is_not_configured(): void
    {
        $this->app->forgetInstance(PrivateFileStore::class);
        $this->app->bind(PrivateFileStore::class, fn () => throw new ConfigurationException('Invalid configuration, please set up your environment.'));

        $this->assertNull(FileUploadHelper::temporaryUrl('members/documents/cac.pdf'));
    }

    public function test_cloudinary_private_store_signs_authenticated_download_urls(): void
    {
        $this->freezeTime();
        $store = new CloudinaryPrivateFileStore(new Cloudinary('cloudinary://key:secret@apfspn-test'));

        $url = $store->temporaryUrl('documents/cac.pdf', now()->addMinutes(10));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://api.cloudinary.com/v1_1/apfspn-test/raw/download', $url);
        $this->assertSame('authenticated', $query['type']);
        $this->assertSame('documents/cac.pdf', $query['public_id']);
        $this->assertSame((string) now()->addMinutes(10)->getTimestamp(), $query['expires_at']);
        $this->assertNotEmpty($query['signature']);
    }

    private function fakePrivateStore(): FakePrivateFileStore
    {
        return tap(new FakePrivateFileStore, fn ($store) => $this->app->instance(PrivateFileStore::class, $store));
    }
}
