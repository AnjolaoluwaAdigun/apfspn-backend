<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ArticleCategory: string
{
    use EnumHelpers;

    case News = 'news';
    case PressRelease = 'press_release';
    case Blog = 'blog';
}
