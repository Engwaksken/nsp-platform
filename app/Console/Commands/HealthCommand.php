<?php

namespace App\Console\Commands;

use App\Operations\Health;
use Illuminate\Console\Command;

/** Prints the health checks; exits with 1 when any check fails (usable from cron or a monitor). */
class HealthCommand extends Command
{
    protected $signature = 'nsp:health';

    protected $description = 'Show system health (scheduler, queue, email, backups, disk and more)';

    public function handle(Health $health): int
    {
        $checks = $health->checks();
        $this->table(['Check', 'Status', 'Detail'], array_map(fn ($c) => [$c['label'], strtoupper($c['status']), $c['detail']], $checks));

        return $health->overall($checks) === 'fail' ? self::FAILURE : self::SUCCESS;
    }
}
