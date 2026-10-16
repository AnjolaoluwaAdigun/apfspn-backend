<?php

namespace App\Services\Articles;

use App\Support\Html\YouTubeEmbedSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class ArticleHtmlSanitizer
{
    public const MAX_LENGTH = 200_000;

    private const TEXT_ELEMENTS = ['h2', 'h3', 'h4', 'p', 'br', 'ul', 'ol', 'li', 'strong', 'em', 'blockquote'];

    private const UNWRAPPED_ELEMENTS = [
        'div', 'span', 'section', 'article', 'header', 'footer', 'main', 'figure', 'figcaption',
        'b', 'i', 'u', 's', 'small', 'mark', 'sub', 'sup', 'code', 'pre', 'h1', 'h5', 'h6', 'font', 'center',
    ];

    private ?HtmlSanitizer $sanitizer = null;

    public function sanitize(string $html): string
    {
        $clean = $this->sanitizer()->sanitize($html);

        return trim(preg_replace([
            '#<iframe(?![^>]*\ssrc=)[^>]*>\s*</iframe>#i',
            '#<img(?![^>]*\ssrc=)[^>]*>#i',
        ], '', $clean));
    }

    private function sanitizer(): HtmlSanitizer
    {
        return $this->sanitizer ??= new HtmlSanitizer($this->config());
    }

    private function config(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig)
            ->defaultAction(HtmlSanitizerAction::Drop)
            ->withMaxInputLength(self::MAX_LENGTH)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['https'])
            ->allowElement('a', ['href', 'title'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
            ->allowElement('iframe', ['src', 'width', 'height', 'title', 'allowfullscreen'])
            ->withAttributeSanitizer(new YouTubeEmbedSanitizer);

        foreach (self::TEXT_ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        foreach (self::UNWRAPPED_ELEMENTS as $element) {
            $config = $config->blockElement($element);
        }

        return $config;
    }
}
