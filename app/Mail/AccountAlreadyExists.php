<?php

namespace App\Mail;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent when someone signs up with an email that already has an account. Always delivered (account security). */
class AccountAlreadyExists extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public bool $singleSignOnOnly) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.Settings::get('site_name').'] You already have an account');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.account-already-exists');
    }
}
