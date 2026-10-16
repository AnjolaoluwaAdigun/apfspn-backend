<?php

namespace App\Helpers\Uploads;

use DateTimeInterface;

interface PrivateFileStore
{
    public function put(string $publicId, string $contents): string;

    public function temporaryUrl(string $publicId, DateTimeInterface $expiresAt): string;
}
