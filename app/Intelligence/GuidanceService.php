<?php

namespace App\Intelligence;

use App\Challenges\ChallengeSearch;
use App\Ecosystem\Recommender as OpportunityRecommender;
use App\Models\Challenges\Challenge;
use App\Models\Ecosystem\Opportunity;
use App\Models\Knowledge\KnowledgeResource;
use App\Models\Registry\Startup;
use App\Models\User;
use App\Registry\ActiveStartup;

/** Deterministic cross-module guidance adapters; never calls an external model. */
class GuidanceService
{
    /** @return list<array{opportunity: Opportunity, reasons: list<string>, unknown: list<string>}> */
    public function opportunitiesForStartup(Startup $startup, User $user): array
    {
        if (! $startup->is($this->activeStartup($user))) {
            return [];
        }

        return OpportunityRecommender::forStartup($startup->loadMissing('profile'), $user, 3);
    }

    /**
     * @param  list<array<string, mixed>>  $gaps
     * @return array<string, list<KnowledgeResource>>
     */
    public function knowledgeForVgiGaps(Startup $startup, User $user, array $gaps): array
    {
        if (! $startup->is($this->activeStartup($user))) {
            return [];
        }

        $dimensions = array_values(array_unique(array_filter(array_map(
            fn (array $gap) => $gap['dimension_key'] ?? null,
            $gaps,
        ))));
        if (! $dimensions) {
            return [];
        }

        $resources = KnowledgeResource::query()->live()->visibleTo($user)->with('category')
            ->latest('published_at')->limit(300)->get();
        $recommendations = [];
        foreach ($dimensions as $dimension) {
            $recommendations[$dimension] = $resources->filter(
                fn (KnowledgeResource $resource) => in_array($dimension, (array) $resource->vgi_dimension_keys, true),
            )->take(3)->values()->all();
        }

        return $recommendations;
    }

    /**
     * @param  list<array<string, mixed>>  $gaps
     * @return array<string, list<Challenge>>
     */
    public function challengesForVgiGaps(Startup $startup, User $user, array $gaps): array
    {
        if (! $startup->is($this->activeStartup($user))) {
            return [];
        }

        $dimensions = array_values(array_unique(array_filter(array_map(
            fn (array $gap) => $gap['dimension_key'] ?? null,
            $gaps,
        ))));
        if (! $dimensions) {
            return [];
        }

        // Reuse the public search boundary and match immutable published metadata only.
        $challenges = app(ChallengeSearch::class)->visible($user)->with('publishedVersion')
            ->orderByDesc('challenges.published_at')->limit(300)->get();
        $recommendations = [];
        foreach ($dimensions as $dimension) {
            $recommendations[$dimension] = $challenges->filter(
                fn (Challenge $challenge) => in_array($dimension, (array) $challenge->publishedVersion?->field('vgi_dimension_keys'), true),
            )->take(3)->values()->all();
        }

        return $recommendations;
    }

    private function activeStartup(User $user): ?Startup
    {
        return ActiveStartup::membership($user)?->startup;
    }
}
