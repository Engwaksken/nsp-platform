<?php

namespace App\Console\Commands;

use App\Integration\OutboxDispatcher;
use App\Support\Features;
use Illuminate\Console\Command;

class DeliverOutbox extends Command
{
    protected $signature = 'registry:deliver-outbox {--limit=100}';

    protected $description = 'Send registry events to connected systems, with retries (runs every minute from the scheduler).';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        if (! Features::enabled('ims_integration')) {
            return self::SUCCESS;
        }
        $planned = $dispatcher->fanOut();
        $result = $dispatcher->deliverDue((int) $this->option('limit'));
        if ($planned || array_sum($result)) {
            $this->info(sprintf('planned=%d delivered=%d failed=%d dead=%d', $planned, $result['delivered'], $result['failed'], $result['dead']));
        }

        return self::SUCCESS;
    }
}
