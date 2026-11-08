<?php

use App\Operations\Health;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| Shared hosting has no long-running queue worker. A single cron entry runs
| `php artisan schedule:run` every minute, and this drains the queue (contact
| notifications) within about a minute. See docs/deployment.md.
*/
// Remind people about saved opportunities 3 days before the deadline.
Schedule::command('opportunities:send-reminders')->dailyAt('08:00')->withoutOverlapping();

// Delete candidates' application CVs after the retention period (Phase 5d).
Schedule::command('talent:purge-cvs')->dailyAt('02:30')->withoutOverlapping();

// Delete old notifications and delivery-log rows (Phase 6a).
Schedule::command('notifications:prune')->dailyAt('03:00')->withoutOverlapping();

// Heartbeat for the health check: proves cron runs the scheduler (Phase 7a).
Schedule::call(fn () => Cache::forever(Health::HEARTBEAT_KEY, time()))->everyMinute()->name('scheduler-heartbeat');

// Nightly encrypted backup, checked after writing, oldest rotated out (Phase 7a).
Schedule::command('nsp:backup')->dailyAt('01:30')->withoutOverlapping();

// Send registry events to connected systems (no-op unless ims_integration is on).
Schedule::command('registry:deliver-outbox')->everyMinute()->withoutOverlapping();

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
