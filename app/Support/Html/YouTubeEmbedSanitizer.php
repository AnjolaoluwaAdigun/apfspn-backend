<?php

namespace App\Support\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\TextSanitizer\UrlSanitizer;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class YouTubeEmbedSanitizer implements AttributeSanitizerInterface
{
    public const HOSTS = ['youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];

    public function getSupportedElements(): ?array
    {
        return ['iframe'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $url = UrlSanitizer::parse($value);

        if (! $url || $url['scheme'] !== 'https' || ! in_array(strtolower((string) $url['host']), self::HOSTS, true)) {
            return null;
        }

        return str_starts_with((string) $url['path'], '/embed/') ? $value : null;
    }
}
