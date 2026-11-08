<?php

namespace App\Jobs;

use App\Communication\Notifier;
use App\Models\Communication\Announcement;
use App\Models\Experts\ExpertProfile;
use App\Models\Funding\InvestorProfile;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Delivers an announcement to its audience in chunks, respecting each person's choices. */
class SendAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public Announcement $announcement) {}

    public static function audienceQuery(string $audience): Builder
    {
        $query = User::query()->where('is_active', true);

        return match ($audience) {
            'founders' => $query->whereIn('id', StartupMembership::query()->where('status', 'active')->whereNotNull('user_id')->select('user_id')),
            'investors' => $query->whereIn('id', InvestorProfile::query()->where('status', 'verified')->select('user_id')),
            'experts' => $query->whereIn('id', ExpertProfile::query()->where('status', 'verified')->select('user_id')),
            default => $query,
        };
    }

    public function handle(Notifier $notifier): void
    {
        $announcement = $this->announcement;
        if ($announcement->status === 'sent') {
            return;
        }
        $count = 0;
        self::audienceQuery($announcement->audience)->orderBy('id')->chunkById(200, function ($users) use ($notifier, $announcement, &$count) {
            foreach ($users as $user) {
                $notifier->notify($user, 'announcements', $announcement->title, [$announcement->body], $announcement->url ?: route('notifications.index'));
                $count++;
            }
        });
        $announcement->forceFill(['status' => 'sent', 'recipients' => $count, 'sent_at' => now()])->save();
    }
}
