<?php

namespace App\Ecosystem;

use App\Models\Ecosystem\Opportunity;
use App\Models\Ecosystem\RecommendationFeedback;
use App\Models\Registry\Startup;
use App\Models\User;

/**
 * Rules-first recommendations (spec §6H): open opportunities where no rule is
 * failed, ranked by how many rules positively match, then by deadline. Every
 * recommendation carries its reasons. Items marked "not relevant" are hidden.
 */
class Recommender
{
    /** @return list<array{opportunity: Opportunity, reasons: list<string>, unknown: list<string>}> */
    public static function forStartup(Startup $startup, User $user, int $limit = 6): array
    {
        $dismissed = RecommendationFeedback::query()->where('user_id', $user->id)->where('item_type', 'opportunity')
            ->where('feedback', 'not_relevant')->pluck('item_id')->all();

        $candidates = Opportunity::query()->open()->whereNotIn('id', $dismissed)
            ->orderByRaw('deadline_at IS NULL')->orderBy('deadline_at')->limit(200)->get();

        $scored = [];
        foreach ($candidates as $opportunity) {
            $checks = Eligibility::check($opportunity, $startup);
            if (collect($checks)->contains(fn ($c) => $c['met'] === false)) {
                continue;
            }
            $met = array_values(array_filter($checks, fn ($c) => $c['met'] === true));
            $unknown = array_values(array_filter($checks, fn ($c) => $c['met'] === null));
            $reasons = array_map(fn ($c) => 'Matches '.strtolower($c['rule']), $met);
            if (! $checks) {
                $reasons[] = 'Open to all startups';
            }
            if ($opportunity->deadline_at && $opportunity->deadline_at->lte(now()->addDays(14))) {
                $reasons[] = 'Closes '.$opportunity->deadline_at->diffForHumans();
            }
            $scored[] = [
                'opportunity' => $opportunity, 'reasons' => $reasons,
                'unknown' => array_map(fn ($c) => $c['rule'], $unknown), 'rank' => count($met),
            ];
        }
        usort($scored, fn ($a, $b) => [$b['rank'], $a['opportunity']->deadline_at?->timestamp ?? PHP_INT_MAX]
            <=> [$a['rank'], $b['opportunity']->deadline_at?->timestamp ?? PHP_INT_MAX]);

        return array_map(fn ($s) => array_diff_key($s, ['rank' => 1]), array_slice($scored, 0, $limit));
    }
}
