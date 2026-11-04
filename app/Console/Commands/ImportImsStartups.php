<?php

namespace App\Console\Commands;

use App\Models\Registry\StartupAlias;
use App\Registry\RegistryService;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * Journey C: match existing IMS startups to the registry. Dry run by default.
 * --apply only links strong matches (same registration number or website);
 * name-only matches are listed for staff review and never linked.
 *
 * CSV columns (header row required): application_id, startup_name, registration_number, website
 */
class ImportImsStartups extends Command
{
    protected $signature = 'registry:import-ims {csv : Path to the CSV export from IMS} {--apply : Link strong matches (default is a dry run)}';

    protected $description = 'Dry-run (or apply) matching of IMS startups to registry records.';

    public function handle(RegistryService $registry): int
    {
        $path = (string) $this->argument('csv');
        if (! is_readable($path) || ($handle = fopen($path, 'r')) === false) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($handle) ?: []);
        foreach (['application_id', 'startup_name'] as $required) {
            if (! in_array($required, $header, true)) {
                $this->error("Missing column: {$required}");

                return self::FAILURE;
            }
        }

        $apply = (bool) $this->option('apply');
        $summary = ['link' => 0, 'already_linked' => 0, 'conflict' => 0, 'review' => 0, 'no_match' => 0];
        $report = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            $applicationId = trim((string) $data['application_id']);
            $matches = $registry->possibleDuplicates([
                'legal_name' => $data['startup_name'] ?? '', 'display_name' => $data['startup_name'] ?? '',
                'registration_number' => $data['registration_number'] ?? null, 'website' => $data['website'] ?? null,
            ]);
            $strong = array_values(array_filter($matches, fn ($m) => $m['strong']));
            $existing = StartupAlias::query()->where('source_system', 'ims')->where('external_id', $applicationId)->first();

            if (count($strong) === 1) {
                $target = $strong[0]['startup'];
                if ($existing) {
                    $outcome = $existing->startup_uuid === $target->uuid ? 'already_linked' : 'conflict';
                } else {
                    $outcome = 'link';
                    if ($apply) {
                        StartupAlias::query()->create(['source_system' => 'ims', 'external_id' => $applicationId, 'startup_uuid' => $target->uuid]);
                    }
                }
                $detail = $target->display_name.' ('.implode(', ', $strong[0]['evidence']).')';
            } elseif (count($strong) > 1) {
                $outcome = 'review';
                $detail = 'Several strong matches: '.implode('; ', array_map(fn ($m) => $m['startup']->display_name, $strong));
            } elseif ($matches) {
                $outcome = 'review';
                $detail = 'Name only: '.implode('; ', array_map(fn ($m) => $m['startup']->display_name, $matches));
            } else {
                $outcome = 'no_match';
                $detail = 'Not registered yet: invite the founders to register';
            }
            $summary[$outcome]++;
            $report[] = [$applicationId, mb_substr((string) $data['startup_name'], 0, 40), $outcome, mb_substr($detail, 0, 80)];
        }
        fclose($handle);

        $this->table(['IMS application', 'Startup name', 'Outcome', 'Detail'], $report);
        $this->info(($apply ? 'APPLIED' : 'DRY RUN, nothing changed').': '.collect($summary)->map(fn ($n, $k) => "$k=$n")->implode(' '));
        if ($apply) {
            Audit::log('integration.ims_import_applied', 'startup_aliases', $summary);
        }

        return self::SUCCESS;
    }
}
