<?php

namespace App\Mail;

use App\Models\Ecosystem\Opportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OpportunityDeadlineReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Opportunity $opportunity) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Deadline soon: '.$this->opportunity->title);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.opportunity-reminder');
    }
}
