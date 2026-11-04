<?php

namespace App\Mail;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JoinRequestReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $startupName,
        public string $requesterName,
        public string $teamUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->requesterName.' asked to join '.$this->startupName.' on '.Settings::get('site_name'));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.join-request');
    }
}
