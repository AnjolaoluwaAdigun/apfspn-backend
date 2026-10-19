<?php

namespace App\Helpers;

use App\Exceptions\ApiException;
use App\Helpers\Uploads\PrivateFileStore;
use Closure;
use Cloudinary\Exception\ConfigurationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

class FileUploadHelper
{
    public static function upload(UploadedFile|string|null $file, string $folder, ?string $current = null, bool $private = false): ?string
    {
        if ($file === null || $file === '') {
            return $current;
        }

        if ($private) {
            return self::storePrivate($file, $folder);
        }

        if ($file instanceof UploadedFile) {
            return self::whenAvailable(fn () => self::disk()->url(self::disk()->putFile($folder, $file)));
        }

        if (Str::startsWith($file, ['http://', 'https://'])) {
            return self::keepUrl($file);
        }

        [$bytes, $extension] = self::decodeBase64($file);
        $path = self::path($folder, $extension);

        return self::whenAvailable(function () use ($path, $bytes) {
            self::disk()->put($path, $bytes);

            return self::disk()->url($path);
        });
    }

    public static function uploadMany(array $files, string $folder): array
    {
        return array_values(array_filter(array_map(fn ($file) => self::upload($file, $folder), $files)));
    }

    public static function temporaryUrl(?string $publicId, ?int $ttlMinutes = null): ?string
    {
        if (! $publicId) {
            return null;
        }

        try {
            return self::privateStore()->temporaryUrl($publicId, now()->addMinutes($ttlMinutes ?? config('apfspn.uploads.private_url_ttl_minutes')));
        } catch (ConfigurationException $exception) {
            report($exception);

            return null;
        }
    }

    public static function deletePrivate(?string $publicId): void
    {
        if (! $publicId) {
            return;
        }

        try {
            self::whenAvailable(fn () => self::privateStore()->delete($publicId));
        } catch (ApiException $exception) {
            if (! $exception->isUploadsUnavailable()) {
                throw $exception;
            }
        }
    }

    public static function isOwnUrl(string $url): bool
    {
        $cloudName = config('apfspn.uploads.cloud_name');
        $parts = parse_url($url);

        return $cloudName
            && ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === config('apfspn.uploads.cloudinary_host')
            && Str::startsWith($parts['path'] ?? '', '/'.$cloudName.'/');
    }

    private static function storePrivate(UploadedFile|string $file, string $folder): string
    {
        if ($file instanceof UploadedFile) {
            if (! $file->isValid()) {
                throw new ApiException('The file could not be read.', 422, ['code' => 'invalid_file']);
            }

            $extension = $file->guessExtension() ?: ($file->getClientOriginalExtension() ?: 'bin');

            return self::whenAvailable(fn () => self::privateStore()->put(self::path($folder, $extension), $file->get()));
        }

        if (Str::startsWith($file, ['http://', 'https://'])) {
            throw new ApiException('Private files must be uploaded, not referenced by URL.', 422, ['code' => 'invalid_file_url']);
        }

        [$bytes, $extension] = self::decodeBase64($file);

        return self::whenAvailable(fn () => self::privateStore()->put(self::path($folder, $extension), $bytes));
    }

    private static function keepUrl(string $url): string
    {
        if (! self::isOwnUrl($url)) {
            throw new ApiException('Only files uploaded to our storage can be referenced by URL.', 422, ['code' => 'invalid_file_url']);
        }

        return $url;
    }

    private static function decodeBase64(string $value): array
    {
        $encoded = preg_replace('/^data:[\w.+\/-]+;base64,/', '', $value);
        $bytes = base64_decode(preg_replace('/\s+/', '', $encoded), true);

        if ($bytes === false || $bytes === '') {
            throw new ApiException('The file could not be read.', 422, ['code' => 'invalid_file']);
        }

        if (strlen($bytes) > config('apfspn.uploads.max_base64_bytes')) {
            throw new ApiException('The file is too large.', 422, ['code' => 'file_too_large']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        return [$bytes, MimeTypes::getDefault()->getExtensions($mime)[0] ?? 'bin'];
    }

    private static function path(string $folder, string $extension): string
    {
        return trim($folder, '/').'/'.Str::uuid().'.'.$extension;
    }

    private static function disk(): FilesystemAdapter
    {
        return Storage::disk(config('apfspn.uploads.disk'));
    }

    private static function whenAvailable(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (ApiException $exception) {
            if ($exception->isUploadsUnavailable()) {
                Log::warning('Cloudinary is not configured, so a file operation was refused. Set CLOUDINARY_URL to enable uploads.');
            }

            throw $exception;
        }
    }

    private static function privateStore(): PrivateFileStore
    {
        return app(PrivateFileStore::class);
    }
}
