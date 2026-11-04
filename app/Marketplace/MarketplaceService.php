<?php

namespace App\Marketplace;

use App\Mail\ServiceNotice;
use App\Models\Marketplace\Inquiry;
use App\Models\Marketplace\Listing;
use App\Models\Marketplace\Message;
use App\Models\Marketplace\Report;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Rules for the marketplace:
 * - listings are published by a startup's owners, founders or admins; staff can take them down;
 * - conversations stay inside NSP: email addresses are not exchanged, and email notices
 *   never contain message text;
 * - the startup's active members can read their conversations; editors reply;
 * - Hive staff see a conversation only when one side reports it.
 */
class MarketplaceService
{
    /** "inquirer", "team" or "team_viewer", or null when the user has no part in the conversation. */
    public function sideOf(User $user, Inquiry $inquiry): ?string
    {
        if ($inquiry->inquirer_id === $user->id) {
            return 'inquirer';
        }
        $startup = $inquiry->startup;
        $membership = $startup?->status === 'active' ? $startup->membershipOf($user) : null;
        if (! $membership) {
            return null;
        }

        return $membership->canEdit() ? 'team' : 'team_viewer';
    }

    public function inquire(Listing $listing, User $user, string $subject, string $body, ?Startup $onBehalfOf): Inquiry
    {
        if (! $listing->isLive()) {
            throw new RegistryException('This listing is not available.');
        }
        if ($listing->startup->membershipOf($user)) {
            throw new RegistryException('This is your own startup’s listing.');
        }
        if ($onBehalfOf && ! $onBehalfOf->membershipOf($user)?->canEdit()) {
            throw new RegistryException('You can only write on behalf of a startup you manage.');
        }
        $open = Inquiry::query()->where('listing_uuid', $listing->uuid)->where('inquirer_id', $user->id)->where('status', 'open')->first();
        if ($open) {
            throw new RegistryException('You already have an open conversation about this listing.');
        }

        $inquiry = Inquiry::query()->create([
            'listing_uuid' => $listing->uuid, 'startup_uuid' => $listing->startup_uuid, 'inquirer_id' => $user->id,
            'inquirer_startup_uuid' => $onBehalfOf?->uuid, 'subject' => mb_substr(trim($subject), 0, 150), 'status' => 'open',
        ]);
        $this->addMessage($inquiry, $user, 'inquirer', $body);
        Audit::log('marketplace.inquiry_opened', $inquiry);

        return $inquiry;
    }

    public function reply(Inquiry $inquiry, User $user, string $body): void
    {
        $side = $this->sideOf($user, $inquiry);
        if (! in_array($side, ['inquirer', 'team'], true)) {
            throw new RegistryException('You cannot reply in this conversation.');
        }
        if ($inquiry->status !== 'open') {
            throw new RegistryException('This conversation is closed.');
        }
        $this->addMessage($inquiry, $user, $side, $body);
    }

    public function close(Inquiry $inquiry, User $user): void
    {
        if (! in_array($this->sideOf($user, $inquiry), ['inquirer', 'team'], true)) {
            throw new RegistryException('You cannot close this conversation.');
        }
        $inquiry->update(['status' => 'closed']);
        Audit::log('marketplace.inquiry_closed', $inquiry);
    }

    public function markRead(Inquiry $inquiry, string $side): void
    {
        $column = $side === 'inquirer' ? 'inquirer_read_at' : 'team_read_at';
        $inquiry->forceFill([$column => now()])->save();
    }

    public function reportListing(Listing $listing, User $user, string $reason, ?string $details): Report
    {
        $report = Report::query()->create(['listing_uuid' => $listing->uuid, 'reporter_id' => $user->id, 'reason' => $reason, 'details' => $details]);
        Audit::log('marketplace.listing_reported', $listing, ['reason' => $reason]);

        return $report;
    }

    public function reportInquiry(Inquiry $inquiry, User $user, string $reason, ?string $details): Report
    {
        if (! in_array($this->sideOf($user, $inquiry), ['inquirer', 'team'], true)) {
            throw new RegistryException('You cannot report this conversation.');
        }
        $report = Report::query()->create(['listing_uuid' => $inquiry->listing_uuid, 'inquiry_uuid' => $inquiry->uuid, 'reporter_id' => $user->id,
            'reason' => $reason, 'details' => $details]);
        Audit::log('marketplace.inquiry_reported', $inquiry, ['reason' => $reason]);

        return $report;
    }

    public function resolve(Report $report, User $staff, string $status, string $note, bool $removeListing): void
    {
        $report->forceFill(['status' => $status, 'resolution_note' => $note, 'resolved_by' => $staff->id, 'resolved_at' => now()])->save();
        if ($removeListing && $report->listing) {
            $report->listing->forceFill(['status' => 'removed', 'moderation_note' => $note])->save();
            Audit::log('marketplace.listing_removed', $report->listing, ['report_id' => $report->id]);
        }
        Audit::log('marketplace.report_'.$status, $report->listing ?? null, ['report_id' => $report->id]);
        $this->notify($report->reporter, 'Your marketplace report was reviewed', [
            'Hive reviewed your report: '.Report::STATUSES[$status].'.',
            'Note from Hive: '.$note,
        ], route('marketplace.index'));
    }

    private function addMessage(Inquiry $inquiry, User $sender, string $side, string $body): Message
    {
        $message = Message::query()->create(['inquiry_uuid' => $inquiry->uuid, 'sender_id' => $sender->id, 'side' => $side, 'body' => mb_substr(trim($body), 0, 3000)]);
        // The sender has read everything; the other side now has something unread.
        $inquiry->forceFill(['last_message_at' => now(), 'inquirer_read_at' => $side === 'inquirer' ? now() : null, 'team_read_at' => $side === 'inquirer' ? null : now()])->save();

        $url = route('marketplace.inquiries.show', $inquiry);
        $title = $inquiry->listing?->title ?? 'a listing';
        if ($side === 'inquirer') {
            foreach (StartupMembership::query()->with('user')->where('startup_uuid', $inquiry->startup_uuid)->where('status', 'active')
                ->whereIn('role', StartupMembership::EDITOR_ROLES)->get() as $member) {
                $this->notify($member->user, 'New message about “'.$title.'”', ['You have a new marketplace message. Read and reply on NSP.'], $url);
            }
        } else {
            $this->notify($inquiry->inquirer, 'Reply about “'.$title.'”', ['The startup replied to your message. Read it on NSP.'], $url);
        }

        return $message;
    }

    private function notify(?User $user, string $subject, array $lines, string $url): void
    {
        if (! $user?->is_active) {
            return;
        }
        try {
            Mail::to($user->email)->queue(new ServiceNotice($subject, $lines, $url));
        } catch (Throwable $exception) {
            Log::warning('[marketplace] notice failed: '.$exception->getMessage());
        }
    }
}
