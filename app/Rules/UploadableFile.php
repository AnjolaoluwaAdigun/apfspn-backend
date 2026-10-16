<?php

namespace App\Rules;

use App\Helpers\FileUploadHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

class UploadableFile implements ValidationRule
{
    public function __construct(
        private readonly array $extensions,
        private readonly bool $allowOwnUrl = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof UploadedFile) {
            $this->validateBytes($value->isValid() ? (string) $value->get() : '', $fail);

            return;
        }

        if (! is_string($value)) {
            $fail('The :attribute must be a file.');

            return;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            if (! $this->allowOwnUrl || ! FileUploadHelper::isOwnUrl($value)) {
                $fail('The :attribute must be uploaded as a file.');
            }

            return;
        }

        $encoded = preg_replace('/^data:[\w.+\/-]+;base64,/', '', $value);
        $this->validateBytes((string) base64_decode(preg_replace('/\s+/', '', $encoded), true), $fail);
    }

    private function validateBytes(string $bytes, Closure $fail): void
    {
        if ($bytes === '') {
            $fail('The :attribute could not be read.');

            return;
        }

        if (strlen($bytes) > config('apfspn.uploads.max_base64_bytes')) {
            $fail('The :attribute must not be larger than 10 MB.');

            return;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        if (! array_intersect(MimeTypes::getDefault()->getExtensions($mime), $this->extensions)) {
            $fail('The :attribute must be a file of type: '.implode(', ', $this->extensions).'.');
        }
    }
}
