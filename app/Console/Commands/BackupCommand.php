<?php

namespace App\Console\Commands;

use App\Operations\Backup;
use Illuminate\Console\Command;
use Throwable;

/** Creates a backup, checks it can be read back, then deletes the oldest beyond NSP_BACKUP_KEEP. */
class BackupCommand extends Command
{
    protected $signature = 'nsp:backup {--verify-only= : Check an existing backup file instead of creating one (use "latest" for the newest)}';

    protected $description = 'Back up the database and stored files, verify the archive and rotate old backups';

    public function handle(Backup $backup): int
    {
        try {
            $file = $this->option('verify-only');
            if ($file === 'latest') {
                $file = $backup->latest();
            }
            if (! $file) {
                $file = $backup->create();
                $this->info('Backup written: '.$file);
            }
            if (! is_file($file)) {
                $this->error('No such backup: '.$file);

                return self::FAILURE;
            }
            $report = $backup->verify($file);
            if (! $report['ok']) {
                $this->error('Backup check FAILED: '.implode(' ', $report['problems']));

                return self::FAILURE;
            }
            $this->info("Backup check passed: {$report['files']} file(s), ".number_format($report['bytes'] / 1048576, 1).' MB.');
            if (! $this->option('verify-only')) {
                $deleted = $backup->rotate();
                if ($deleted) {
                    $this->info("Deleted {$deleted} old backup(s).");
                }
            }
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
