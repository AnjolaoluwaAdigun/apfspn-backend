<?php

namespace App\Jobs;

use App\Helpers\FileUploadHelper;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeletePrivateFile implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $publicId) {}

    public function uniqueId(): string
    {
        return $this->publicId;
    }

    public function handle(): void
    {
        FileUploadHelper::deletePrivate($this->publicId);
    }
}
