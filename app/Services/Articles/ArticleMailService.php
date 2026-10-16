<?php

namespace App\Services\Articles;

use App\Enums\ArticleNotice;
use App\Mail\ArticleNoticeMail;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use Illuminate\Support\Facades\Mail;

class ArticleMailService
{
    public function __construct(private readonly ArticleRepositoryInterface $articles) {}

    public function send(string $articleUuid, ArticleNotice $notice): void
    {
        $article = $this->articles->findWithAuthor($articleUuid);

        if (! $article?->author?->isActive()) {
            return;
        }

        Mail::to($article->author->email)->send(new ArticleNoticeMail(
            $notice,
            $article->author->name,
            $article->title,
            $article->review_note,
            rtrim(config('apfspn.frontend.admin_url'), '/').'/articles/'.$article->getKey(),
        ));
    }
}
