<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ArticleStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function canBeSubmitted(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }

    public function canBePublished(): bool
    {
        return in_array($this, [self::Draft, self::InReview, self::ChangesRequested, self::Scheduled], true);
    }

    public function canBeScheduled(): bool
    {
        return in_array($this, [self::Draft, self::InReview, self::ChangesRequested, self::Scheduled], true);
    }

    public function canBeSentBack(): bool
    {
        return in_array($this, [self::InReview, self::Scheduled], true);
    }

    public function isEditable(): bool
    {
        return $this !== self::Archived;
    }

    public function isEditableByAuthor(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }

    public function isDeletable(): bool
    {
        return in_array($this, [self::Draft, self::Archived], true);
    }
}
