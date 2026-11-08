<?php

namespace App\Console\Commands;

use App\Communication\Notifier;
use App\Mail\OpportunityDeadlineReminder;
use App\Models\Ecosystem\OpportunityBookmark;
use App\Support\Features;
use Illuminate\Console\Command;

class SendOpportunityReminders extends Command
{
    protected $signature = 'opportunities:send-reminders {--days=3}';

    protected $description = 'Email people about saved opportunities whose deadline is near (once per bookmark).';

    public function handle(Notifier $notifier): int
    {
        if (! Features::enabled('opportunities')) {
            return self::SUCCESS;
        }
        $days = max(1, (int) $this->option('days'));
        $due = OpportunityBookmark::query()->with('opportunity', 'user')->whereNull('reminded_at')
            ->whereHas('opportunity', fn ($q) => $q->where('status', 'published')->whereBetween('deadline_at', [now(), now()->addDays($days)]))
            ->get();

        foreach ($due as $bookmark) {
            if (! $bookmark->user?->is_active) {
                continue;
            }
            $notifier->notify($bookmark->user, 'opportunities', 'Deadline soon: '.$bookmark->opportunity->title,
                ['An opportunity you saved closes on '.$bookmark->opportunity->deadline_at?->format('j F Y').'.'],
                route('opportunities.show', $bookmark->opportunity), new OpportunityDeadlineReminder($bookmark->opportunity));
            $bookmark->update(['reminded_at' => now()]);
        }
        if ($due->count()) {
            $this->info($due->count().' reminder(s) queued.');
        }

        return self::SUCCESS;
    }
}
