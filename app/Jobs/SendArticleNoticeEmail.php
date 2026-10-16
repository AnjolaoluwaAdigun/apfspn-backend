<?php

namespace App\Jobs;

use App\Enums\ArticleNotice;
use App\Services\Articles\ArticleMailService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendArticleNoticeEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $articleUuid,
        public readonly ArticleNotice $notice,
        public readonly string $occurredAt,
    ) {}

    public function uniqueId(): string
    {
        return implode(':', [$this->articleUuid, $this->notice->value, $this->occurredAt]);
    }

    public function handle(ArticleMailService $mail): void
    {
        $mail->send($this->articleUuid, $this->notice);
    }
}
