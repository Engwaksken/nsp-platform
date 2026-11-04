<?php

use Illuminate\Support\Facades\Schedule;

/*
| Shared hosting has no long-running queue worker. A single cron entry runs
| `php artisan schedule:run` every minute, and this drains the queue (contact
| notifications) within about a minute. See docs/deployment.md.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
