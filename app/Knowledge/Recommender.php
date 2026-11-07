<?php

namespace App\Knowledge;

use App\Models\Knowledge\KnowledgeResource;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Explainable recommendations: every suggestion carries the plain-language reasons it was picked.
 * Signals: the user's audience (from roles and startup membership), the startup's stage and its
 * weakest VGI dimensions (when the VGI module exposes them). Only resources the user may open are considered.
 */
class Recommender
{
    private const CANDIDATE_POOL = 300;

    public function __construct(private VgiSignals $vgi) {}

    /** @return Collection<int, array{resource: KnowledgeResource, reasons: array<string>}> */
    public function recommend(User $user, int $limit = 6): Collection
    {
        $context = $this->context($user);
        if (! $context['audiences'] && ! $context['weakest'] && ! $context['stage']) {
            return collect();
        }

        return KnowledgeResource::query()->live()->visibleTo($user)->with('category')
            ->latest('published_at')->limit(self::CANDIDATE_POOL)->get()
            ->map(fn (KnowledgeResource $r) => ['resource' => $r] + $this->score($r, $context))
            ->filter(fn ($row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn ($row) => ['resource' => $row['resource'], 'reasons' => $row['reasons']])
            ->values();
    }

    /** Why a single resource suits this user (empty when it doesn't match their signals). */
    public function reasonsFor(?User $user, KnowledgeResource $resource): array
    {
        return $user ? $this->score($resource, $this->context($user))['reasons'] : [];
    }

    /** @return array{audiences: array<string>, weakest: array<string>, stage: ?string} */
    public function context(User $user): array
    {
        $audiences = [];
        $weakest = [];
        $stage = null;
        $startups = KnowledgeAccess::activeStartups($user);
        if ($startups->isNotEmpty()) {
            $audiences[] = 'founders';
            foreach ($startups as $startup) {
                $signals = $this->vgi->for($startup);
                $weakest = array_merge($weakest, $signals['weakest']);
                $stage ??= $signals['stage'];
            }
        }
        if ($user->hasRole('investor')) {
            $audiences[] = 'investors';
        }
        if ($user->hasRole('expert') || $user->hasRole('consultant')) {
            $audiences[] = 'experts';
        }

        return ['audiences' => array_values(array_unique($audiences)), 'weakest' => array_values(array_unique($weakest)), 'stage' => $stage];
    }

    /** @return array{score: int, reasons: array<string>} */
    private function score(KnowledgeResource $resource, array $context): array
    {
        $score = 0;
        $reasons = [];
        $dimensions = config('knowledge.vgi_dimensions', []);
        foreach (array_intersect($context['weakest'], (array) $resource->vgi_dimension_keys) as $key) {
            $score += 3;
            $reasons[] = 'Matches your weakest area: '.($dimensions[$key] ?? $key);
        }
        if ($context['stage'] && in_array($context['stage'], (array) $resource->stages, true)) {
            $score += 2;
            $reasons[] = 'Fits your stage: '.config('knowledge.stages.'.$context['stage'], $context['stage']);
        }
        $audiences = config('knowledge.audiences', []);
        foreach (array_intersect($context['audiences'], (array) $resource->audience) as $key) {
            $score += 1;
            $reasons[] = 'Written for '.mb_strtolower($audiences[$key] ?? $key);
        }

        return ['score' => $score, 'reasons' => $reasons];
    }
}
