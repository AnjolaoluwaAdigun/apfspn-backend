<?php

namespace App\Helpers\Uploads;

use App\Exceptions\ApiException;
use Cloudinary\Cloudinary;

class CloudinaryClient
{
    public static function make(array $disk): Cloudinary
    {
        if (filled($disk['url'] ?? null)) {
            return new Cloudinary($disk['url']);
        }

        if (! filled($disk['cloud'] ?? null) || ! filled($disk['key'] ?? null) || ! filled($disk['secret'] ?? null)) {
            throw ApiException::uploadsUnavailable();
        }

        return new Cloudinary([
            'cloud' => [
                'cloud_name' => $disk['cloud'],
                'api_key' => $disk['key'],
                'api_secret' => $disk['secret'],
            ],
            'url' => [
                'secure' => $disk['secure'] ?? false,
            ],
        ]);
    }
}
