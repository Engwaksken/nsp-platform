<?php

namespace App\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * System health checks for the admin page, the `nsp:health` command and the uptime endpoint.
 * Each check returns ok, warn or fail with a short explanation. No personal data.
 */
class Health
{
    public const HEARTBEAT_KEY = 'nsp:scheduler-heartbeat';

    /** @return list<array{key: string, label: string, status: string, detail: string}> */
    public function checks(): array
    {
        $production = app()->environment('production');
        $checks = [];

        $checks[] = $this->check('app', 'Application settings', function () use ($production) {
            if ($production && config('app.debug')) {
                return ['fail', 'APP_DEBUG is on in production: error pages would show internal details.'];
            }
            if ($production && ! str_starts_with((string) config('app.url'), 'https://')) {
                return ['warn', 'APP_URL does not use https.'];
            }

            return ['ok', 'Environment: '.app()->environment().'.'];
        });

        $checks[] = $this->check('database', 'Database', function () {
            DB::select('select 1');

            return ['ok', 'Connected ('.DB::connection()->getDriverName().').'];
        });

        $checks[] = $this->check('scheduler', 'Scheduler (cron)', function () {
            $beat = Cache::get(self::HEARTBEAT_KEY);
            if (! $beat) {
                return ['fail', 'No heartbeat yet. Check the cron entry for `php artisan schedule:run`.'];
            }
            $minutes = (int) floor((time() - (int) $beat) / 60);

            return $minutes > 5 ? ['fail', "Last ran {$minutes} minutes ago."] : ['ok', 'Ran within the last 5 minutes.'];
        });

        $checks[] = $this->check('queue', 'Queue', function () {
            $waiting = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('available_at');
            $age = $oldest ? (int) floor((time() - (int) $oldest) / 60) : 0;
            if ($age > 30) {
                return ['fail', "{$waiting} job(s) waiting; the oldest for {$age} minutes."];
            }

            return [$age > 10 ? 'warn' : 'ok', "{$waiting} job(s) waiting".($age ? "; oldest {$age} min" : '').'.'];
        });

        $checks[] = $this->check('failed_jobs', 'Failed jobs (24 hours)', function () {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

            return [$failed > 0 ? 'warn' : 'ok', "{$failed} failed job(s)."];
        });

        $checks[] = $this->check('integrations', 'Deliveries to connected systems', function () {
            $dead = DB::table('webhook_deliveries')->where('status', 'dead')->where('updated_at', '>=', now()->subDays(7))->count();
            $stuck = DB::table('webhook_deliveries')->where('status', 'pending')->where('created_at', '<', now()->subDay())->count();
            if ($dead > 0) {
                return ['warn', "{$dead} delivery(ies) gave up in the last 7 days; replay them in Admin › Integrations."];
            }

            return [$stuck > 0 ? 'warn' : 'ok', "{$stuck} delivery(ies) pending for over a day."];
        });

        $checks[] = $this->check('email', 'Email delivery (24 hours)', function () use ($production) {
            $failed = DB::table('email_deliveries')->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
            $sent = DB::table('email_deliveries')->where('status', 'sent')->where('created_at', '>=', now()->subDay())->count();
            if ($production && in_array(config('mail.default'), ['log', 'array'], true)) {
                return ['fail', 'MAIL_MAILER is '.config('mail.default').': no email leaves the server.'];
            }
            if ($failed > 0 && $failed >= $sent) {
                return ['fail', "{$failed} failed and {$sent} sent. Check the SMTP settings."];
            }

            return [$failed > 0 ? 'warn' : 'ok', "{$sent} sent, {$failed} failed."];
        });

        $checks[] = $this->check('backup', 'Backups', function () use ($production) {
            $latest = app(Backup::class)->latest();
            if (! $latest) {
                return [$production ? 'fail' : 'warn', 'No backup found in '.config('nsp.backup.path').'.'];
            }
            $hours = (int) floor((time() - filemtime($latest)) / 3600);
            if ($hours > 72) {
                return ['fail', "The latest backup is {$hours} hours old."];
            }

            return [$hours > 26 ? 'warn' : 'ok', 'Latest: '.basename($latest)." ({$hours} h old).".((string) config('nsp.backup.password') === '' ? ' Not encrypted.' : '')];
        });

        $checks[] = $this->check('disk', 'Disk space', function () {
            $free = @disk_free_space(storage_path());
            $total = @disk_total_space(storage_path());
            if (! $free || ! $total) {
                return ['warn', 'Could not read disk space.'];
            }
            $percent = (int) round($free / $total * 100);
            $gb = round($free / 1073741824, 1);
            if ($percent < 5 || $free < 536870912) {
                return ['fail', "{$gb} GB free ({$percent}%)."];
            }

            return [$percent < 15 ? 'warn' : 'ok', "{$gb} GB free ({$percent}%)."];
        });

        return $checks;
    }

    /** ok, warn or fail for the whole system: the worst of the checks. */
    public function overall(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        return in_array('fail', $statuses, true) ? 'fail' : (in_array('warn', $statuses, true) ? 'warn' : 'ok');
    }

    private function check(string $key, string $label, callable $test): array
    {
        try {
            [$status, $detail] = $test();
        } catch (Throwable $e) {
            [$status, $detail] = ['fail', 'Check failed: '.mb_substr($e->getMessage(), 0, 200)];
        }

        return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail];
    }
}
