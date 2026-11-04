<?php

namespace App\Listeners;

use App\Models\Communication\EmailDelivery;
use Illuminate\Mail\Events\MessageSent;

/** Marks a logged delivery as sent once the mailer hands the message over. */
class MarkEmailDelivered
{
    public function handle(MessageSent $event): void
    {
        $header = $event->message->getHeaders()->get('X-NSP-Delivery');
        if ($header && ctype_digit($id = trim($header->getBodyAsString()))) {
            EmailDelivery::query()->whereKey((int) $id)->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
        }
    }
}
