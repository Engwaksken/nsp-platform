<?php

namespace App\Ecosystem;

use App\Models\Ecosystem\Opportunity;
use App\Models\Growth\VgiAssessment;
use App\Models\Registry\Startup;

/**
 * Explainable eligibility: each rule an opportunity sets is checked against
 * the startup's profile. A rule is met (true), not met (false) or unknown
 * (null, the profile lacks the data). Rules the opportunity does not set are
 * not shown. This is guidance only; the organiser decides.
 *
 * eligibility JSON: {"sectors": [...], "countries": ["UG", ...], "stages": ["Validation", ...],
 *                    "registered_only": true, "max_age_years": 5}
 */
class Eligibility
{
    /** @return list<array{rule: string, met: ?bool, detail: string}> */
    public static function check(Opportunity $opportunity, Startup $startup): array
    {
        $rules = $opportunity->eligibility ?? [];
        $profile = $startup->profile;
        $checks = [];

        if (! empty($rules['sectors'])) {
            $sector = $profile?->sector;
            $checks[] = ['rule' => 'Sector', 'met' => $sector ? in_array($sector, $rules['sectors'], true) : null,
                'detail' => 'Open to: '.implode(', ', $rules['sectors']).($sector ? '. Yours: '.$sector : '. Add your sector to your profile.')];
        }
        if (! empty($rules['countries'])) {
            $names = array_map(fn ($c) => Startup::COUNTRIES[$c] ?? $c, $rules['countries']);
            $checks[] = ['rule' => 'Country', 'met' => in_array($startup->country, $rules['countries'], true),
                'detail' => 'Open to: '.implode(', ', $names).'. Yours: '.(Startup::COUNTRIES[$startup->country] ?? $startup->country)];
        }
        if (! empty($rules['stages'])) {
            $stage = self::stage($startup);
            $checks[] = ['rule' => 'Stage', 'met' => $stage ? in_array($stage, $rules['stages'], true) : null,
                'detail' => 'For startups at: '.implode(', ', $rules['stages']).($stage ? '. Your latest growth assessment: '.$stage : '. Take the growth assessment to see your stage.')];
        }
        if (! empty($rules['registered_only'])) {
            $checks[] = ['rule' => 'Registered company', 'met' => filled($startup->registration_number),
                'detail' => filled($startup->registration_number) ? 'Your registration number is on your profile.' : 'Add your registration number if you are registered.'];
        }
        if (! empty($rules['max_age_years'])) {
            $year = $startup->year_founded;
            $checks[] = ['rule' => 'Company age', 'met' => $year ? (now()->year - $year) <= (int) $rules['max_age_years'] : null,
                'detail' => 'Founded within the last '.(int) $rules['max_age_years'].' years'.($year ? '. Yours: '.$year : '. Add your founding year.')];
        }

        return $checks;
    }

    public static function stage(Startup $startup): ?string
    {
        return VgiAssessment::query()->where('startup_uuid', $startup->uuid)->where('status', 'submitted')
            ->latest('submitted_at')->first()?->displayBand();
    }

    /**
     * The current stage of many startups in one query (for lists).
     *
     * @param  iterable<string>  $uuids
     * @return array<string, string|null> startup uuid => stage
     */
    public static function stagesFor(iterable $uuids): array
    {
        $uuids = collect($uuids)->unique()->values();
        $stages = $uuids->mapWithKeys(fn ($uuid) => [$uuid => null])->all();
        if ($uuids->isEmpty()) {
            return $stages;
        }
        VgiAssessment::query()->whereIn('startup_uuid', $uuids)->where('status', 'submitted')
            ->orderByDesc('submitted_at')->get(['startup_uuid', 'band_label', 'override_band_label', 'submitted_at'])
            ->each(function (VgiAssessment $assessment) use (&$stages) {
                $stages[$assessment->startup_uuid] ??= $assessment->displayBand();
            });

        return $stages;
    }
}
