<?php

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

// Send registry events to connected systems (no-op unless ims_integration is on).
Schedule::command('registry:deliver-outbox')->everyMinute()->withoutOverlapping();

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
