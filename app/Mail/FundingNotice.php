<?php

namespace App\Mail;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Short notices about introductions and data-room access. Never includes private startup data. */
class FundingNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $subjectLine, public array $lines, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.Settings::get('site_name').'] '.$this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.funding-notice');
    }
}
