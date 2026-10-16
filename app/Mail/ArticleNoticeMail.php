<?php

namespace App\Mail;

use App\Enums\ArticleNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ArticleNoticeMail extends Mailable
{
    public function __construct(
        public readonly ArticleNotice $notice,
        public readonly string $name,
        public readonly string $title,
        public readonly ?string $reviewNote,
        public readonly string $articleUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notice->subject($this->title));
    }

    public function content(): Content
    {
        return new Content(view: $this->notice->view());
    }
}
