<?php

namespace App\Helpers\Uploads;

use App\Exceptions\ApiException;
use Closure;
use Cloudinary\Cloudinary;
use DateTimeInterface;

class CloudinaryPrivateFileStore implements PrivateFileStore
{
    private const DELIVERY_TYPE = 'authenticated';

    private const RESOURCE_TYPE = 'raw';

    private ?Cloudinary $cloudinary = null;

    public function __construct(private readonly Closure $resolveClient) {}

    public function put(string $publicId, string $contents): string
    {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $contents);
        rewind($stream);

        try {
            $result = $this->client()->uploadApi()->upload($stream, [
                'public_id' => $publicId,
                'type' => self::DELIVERY_TYPE,
                'resource_type' => self::RESOURCE_TYPE,
                'overwrite' => false,
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $result['public_id'];
    }

    public function temporaryUrl(string $publicId, DateTimeInterface $expiresAt): ?string
    {
        try {
            $client = $this->client();
        } catch (ApiException $exception) {
            if ($exception->isUploadsUnavailable()) {
                return null;
            }

            throw $exception;
        }

        return $client->uploadApi()->privateDownloadUrl($publicId, '', [
            'type' => self::DELIVERY_TYPE,
            'resource_type' => self::RESOURCE_TYPE,
            'expires_at' => $expiresAt->getTimestamp(),
        ]);
    }

    public function delete(string $publicId): void
    {
        $this->client()->uploadApi()->destroy($publicId, [
            'type' => self::DELIVERY_TYPE,
            'resource_type' => self::RESOURCE_TYPE,
            'invalidate' => true,
        ]);
    }

    private function client(): Cloudinary
    {
        return $this->cloudinary ??= ($this->resolveClient)();
    }
}
