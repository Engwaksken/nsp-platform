<?php

namespace App\Organizations;

use App\Models\Funding\FundingRequest;
use App\Models\Growth\VgiAssessment;
use App\Models\Integration\ProgramRecord;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupProfile;

/**
 * Builds the read-only, scope-filtered summary an organization sees for one
 * portfolio startup. Only scopes passed in are read at all, so data outside a
 * grant is never loaded, let alone rendered. Each section is a list of
 * [label, value] string rows; null means "nothing to show yet".
 */
class PortfolioSnapshot
{
    /**
     * @param  list<string>  $scopes  granted scopes (already checked by PortfolioAccess)
     * @return array<string, list<array{0: string, 1: string}>|null>
     */
    public function for(Startup $startup, array $scopes): array
    {
        $sections = [];
        foreach (PortfolioAccess::scopeKeys() as $scope) {
            if (in_array($scope, $scopes, true)) {
                $sections[$scope] = $this->section($startup, $scope);
            }
        }

        return $sections;
    }

    /** @return list<array{0: string, 1: string}>|null */
    private function section(Startup $startup, string $scope): ?array
    {
        $provider = config('organizations.scope_providers.'.$scope);
        if ($provider && class_exists($provider)) {
            return app($provider)->summary($startup);
        }

        return match ($scope) {
            'profile_summary' => $this->profile($startup),
            'vgi_summary' => $this->vgi($startup),
            'funding_summary' => $this->funding($startup),
            'programme_outcomes' => $this->programmes($startup),
            default => null,
        };
    }

    private function profile(Startup $startup): array
    {
        /** @var StartupProfile|null $profile */
        $profile = $startup->profile;

        return array_values(array_filter([
            ['Name', $startup->display_name],
            ['Country', Startup::COUNTRIES[$startup->country] ?? (string) $startup->country],
            ['Year founded', (string) ($startup->year_founded ?? '')],
            ['Sector', (string) ($profile?->sector ?? '')],
            ['Team size', (string) ($profile?->team_size ?? '')],
            ['Website', (string) ($startup->website ?? '')],
            ['About', (string) ($startup->description ?? '')],
        ], fn ($row) => $row[1] !== ''));
    }

    private function vgi(Startup $startup): ?array
    {
        $assessment = VgiAssessment::query()->with('framework')->where('startup_uuid', $startup->uuid)
            ->where('status', 'submitted')->latest('submitted_at')->first();
        if (! $assessment) {
            return null;
        }

        return [
            ['Growth score', number_format((float) $assessment->displayScore(), 1).' / 100'],
            ['Band', (string) ($assessment->displayBand() ?? '—')],
            ['Assessed', $assessment->submitted_at?->format('j M Y') ?? '—'],
            ['Rubric', $assessment->framework ? $assessment->framework->name.' v'.$assessment->framework->version : '—'],
        ];
    }

    private function funding(Startup $startup): ?array
    {
        $request = FundingRequest::query()->where('startup_uuid', $startup->uuid)->where('status', 'open')->latest('id')->first();
        if (! $request) {
            return [['Raising now', 'No']];
        }

        return [
            ['Raising now', 'Yes'],
            ['Amount sought', $request->amountLabel()],
            ['Instruments', implode(', ', array_map(fn ($i) => str_replace('_', ' ', (string) $i), (array) $request->instruments)) ?: '—'],
        ];
    }

    private function programmes(Startup $startup): ?array
    {
        $records = ProgramRecord::query()->where('startup_uuid', $startup->uuid)->orderByDesc('enrolled')->orderByDesc('submitted_at')->limit(20)->get();
        if ($records->isEmpty()) {
            return null;
        }

        return $records->map(fn (ProgramRecord $r) => [
            trim(($r->program_title ?? 'Programme').($r->cohort_name ? ' · '.$r->cohort_name : '')),
            ucfirst(str_replace('_', ' ', $r->status)).($r->enrolled ? ' (enrolled)' : ''),
        ])->all();
    }
}
