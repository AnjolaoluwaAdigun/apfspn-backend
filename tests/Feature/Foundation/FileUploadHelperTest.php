<?php

namespace Tests\Feature\Foundation;

use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
}
