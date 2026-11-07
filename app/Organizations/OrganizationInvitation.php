<?php

namespace App\Organizations;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Invitation to join an organization. Account email: always sent, carries no portfolio data. */
class OrganizationInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $organizationName,
        public string $inviterName,
        public string $roleLabel,
        public string $acceptUrl,
        public int $days,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->inviterName.' invited you to join '.$this->organizationName.' on '.Settings::get('site_name'));
    }

    public function content(): Content
    {
        return new Content(text: 'organizations.mail.invitation');
    }
}
