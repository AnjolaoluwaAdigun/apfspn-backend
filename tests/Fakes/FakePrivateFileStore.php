<?php

namespace Tests\Fakes;

use App\Helpers\Uploads\PrivateFileStore;
use DateTimeInterface;

class FakePrivateFileStore implements PrivateFileStore
{
    public array $files = [];

    public function put(string $publicId, string $contents): string
    {
        $this->files[$publicId] = $contents;

        return $publicId;
    }

    public function temporaryUrl(string $publicId, DateTimeInterface $expiresAt): string
    {
        return 'https://api.cloudinary.com/v1_1/apfspn-test/raw/download?'.http_build_query([
            'public_id' => $publicId,
            'type' => 'authenticated',
            'expires_at' => $expiresAt->getTimestamp(),
        ]);
    }

    public function delete(string $publicId): void
    {
        unset($this->files[$publicId]);
    }
}
