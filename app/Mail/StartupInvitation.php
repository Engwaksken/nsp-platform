<?php

namespace App\Mail;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StartupInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $startupName,
        public string $inviterName,
        public string $roleLabel,
        public string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->inviterName.' invited you to join '.$this->startupName.' on '.Settings::get('site_name'));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.startup-invitation');
    }
}
