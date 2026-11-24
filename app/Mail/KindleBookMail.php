<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class KindleBookMail extends Mailable
{
    public function __construct(public string $filePath, public string $attachmentName, public string $format, public string $bookTitle) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.mailers.kindle.username'), 'Minha Biblioteca'),
            subject: 'Livro: '.mb_substr(preg_replace('/[\r\n]+/', ' ', $this->bookTitle), 0, 180),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.kindle-book');
    }

    public function attachments(): array
    {
        return [Attachment::fromPath($this->filePath)->as($this->attachmentName)
            ->withMime($this->format === 'epub' ? 'application/epub+zip' : 'application/pdf')];
    }
}
