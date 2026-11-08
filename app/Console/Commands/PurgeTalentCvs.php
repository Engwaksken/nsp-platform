<?php

namespace App\Console\Commands;

use App\Support\Features;
use App\Talent\TalentService;
use Illuminate\Console\Command;

/** Deletes copies of candidates' CVs once the retention period has passed (config nsp.talent.cv_retention_days). */
class PurgeTalentCvs extends Command
{
    protected $signature = 'talent:purge-cvs';

    protected $description = 'Delete application CVs kept longer than the retention period';

    public function handle(TalentService $talent): int
    {
        if (! Features::enabled('talent')) {
            $this->info('Talent is switched off; nothing to do.');

            return self::SUCCESS;
        }
        $count = $talent->purgeExpiredCvs();
        $this->info("Purged {$count} application CV(s).");

        return self::SUCCESS;
    }
}
