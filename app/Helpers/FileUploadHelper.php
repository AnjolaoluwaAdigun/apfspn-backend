<?php

namespace App\Helpers;

use App\Exceptions\ApiException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

class FileUploadHelper
{
    public static function upload(UploadedFile|string|null $file, string $folder, ?string $current = null): ?string
    {
        if ($file === null || $file === '') {
            return $current;
        }

        if ($file instanceof UploadedFile) {
            return self::disk()->url(self::disk()->putFile($folder, $file));
        }

        if (Str::startsWith($file, ['http://', 'https://'])) {
            return self::keepUrl($file);
        }

        return self::storeBase64($file, $folder);
    }

    public static function uploadMany(array $files, string $folder): array
    {
        return array_values(array_filter(array_map(fn ($file) => self::upload($file, $folder), $files)));
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

    private static function keepUrl(string $url): string
    {
        if (! self::isOwnUrl($url)) {
            throw new ApiException('Only files uploaded to our storage can be referenced by URL.', 422, ['code' => 'invalid_file_url']);
        }

        return $url;
    }

    private static function storeBase64(string $value, string $folder): string
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
        $extension = MimeTypes::getDefault()->getExtensions($mime)[0] ?? 'bin';
        $path = trim($folder, '/').'/'.Str::uuid().'.'.$extension;

        self::disk()->put($path, $bytes);

        return self::disk()->url($path);
    }

    private static function disk(): FilesystemAdapter
    {
        return Storage::disk(config('apfspn.uploads.disk'));
    }
}
