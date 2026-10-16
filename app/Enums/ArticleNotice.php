<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ArticleNotice: string
{
    use EnumHelpers;

    case SentBack = 'sent_back';
    case Published = 'published';

    public function subject(string $title): string
    {
        return match ($this) {
            self::SentBack => 'Changes requested: '.$title,
            self::Published => 'Published: '.$title,
        };
    }

    public function view(): string
    {
        return 'mail.article-'.str_replace('_', '-', $this->value);
    }
}
