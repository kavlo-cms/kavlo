<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KavloTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $rendered,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->rendered['subject'] ?? config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.template',
            text: 'emails.template_text',
            with: [
                'html' => $this->rendered['html'] ?? '',
                'text' => $this->rendered['text'] ?? '',
            ],
        );
    }
}
