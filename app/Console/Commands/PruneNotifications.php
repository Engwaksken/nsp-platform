<?php

namespace App\Console\Commands;

use App\Models\Communication\EmailDelivery;
use App\Models\Communication\UserNotification;
use Illuminate\Console\Command;

/** Deletes old notifications and delivery-log rows (periods in config nsp.notifications). */
class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete old in-app notifications and email delivery records';

    public function handle(): int
    {
        $read = UserNotification::query()->whereNotNull('read_at')->where('created_at', '<', now()->subDays((int) config('nsp.notifications.keep_read_days', 90)))->delete();
        $unread = UserNotification::query()->where('created_at', '<', now()->subDays((int) config('nsp.notifications.keep_unread_days', 365)))->delete();
        $log = EmailDelivery::query()->where('created_at', '<', now()->subDays((int) config('nsp.notifications.keep_delivery_log_days', 180)))->delete();
        $this->info("Deleted {$read} read and {$unread} old notifications, and {$log} delivery records.");

        return self::SUCCESS;
    }
}
