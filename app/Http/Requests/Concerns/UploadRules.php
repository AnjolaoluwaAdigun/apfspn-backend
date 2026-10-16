<?php

namespace App\Http\Requests\Concerns;

use App\Rules\UploadableFile;

trait UploadRules
{
    protected function documentRules(string $field): array
    {
        return [$field => ['required', new UploadableFile(['pdf', 'jpg', 'jpeg', 'png'])]];
    }

    protected function photoRules(string $field = 'photo'): array
    {
        return [$field => ['nullable', new UploadableFile(['jpg', 'jpeg', 'png', 'webp'], allowOwnUrl: true)]];
    }
}
