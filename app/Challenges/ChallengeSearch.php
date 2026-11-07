<?php

namespace App\Challenges;

use App\Models\Challenges\Challenge;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The only way the public side queries challenges. The visibility filter is ALWAYS applied first:
 * only live published snapshots are searched, so drafts, submissions under review, rejected and
 * archived challenges and unpublished revision text can never be returned. Members-only challenges
 * are returned to signed-in users only.
 *
 * Search is a portable DB LIKE over the published snapshot (title, problem, region, tags). A semantic
 * index could replace the keyword step later, but must keep calling visible() first.
 */
class ChallengeSearch
{
    public const SORTS = ['newest' => 'Newest', 'priority' => 'Highest priority', 'title' => 'Title A–Z', 'reviewed' => 'Recently reviewed'];

    /** Live, published challenges this viewer may see, joined to their published snapshot as `pv`. */
    public function visible(?User $viewer): Builder
    {
        return Challenge::query()
            ->select('challenges.*')
            ->join('challenge_versions as pv', function ($join) {
                $join->on('pv.id', '=', 'challenges.published_version_id')
                    ->on('pv.challenge_id', '=', 'challenges.id');
            })
            ->whereNotNull('pv.published_at')
            ->whereNull('challenges.archived_at')
            ->where('challenges.status', '!=', 'archived')
            ->when($viewer === null, fn (Builder $q) => $q->where('pv.visibility', 'public'));
    }

    /** Normalise filters from a request, dropping anything not in the allowed lists. */
    public static function criteriaFrom(Request $request): array
    {
        $pick = fn (string $key, array $allowed) => in_array($request->query($key), $allowed, true) ? (string) $request->query($key) : null;

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'sector' => $pick('sector', StartupProfile::SECTORS),
            'country' => $pick('country', array_keys(Startup::COUNTRIES)),
            'priority' => $pick('priority', array_keys(config('challenges.priorities'))),
            'issuer_type' => $pick('issuer_type', array_keys(config('challenges.issuer_types'))),
            'category' => $pick('category', array_keys(config('challenges.categories'))),
            'sort' => $pick('sort', array_keys(self::SORTS)) ?? 'newest',
        ];
    }

    public function search(array $criteria, ?User $viewer, ?int $perPage = null): LengthAwarePaginator
    {
        $query = $this->visible($viewer);

        foreach (['sector', 'country', 'priority', 'issuer_type', 'category'] as $field) {
            if (! empty($criteria[$field])) {
                $query->where('pv.'.$field, $criteria[$field]);
            }
        }

        $terms = array_slice(array_filter(preg_split('/\s+/', (string) ($criteria['q'] ?? '')) ?: []), 0, 5);
        foreach ($terms as $term) {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $query->where(function (Builder $w) use ($like) {
                foreach (['pv.title', 'pv.problem_description', 'pv.region', 'pv.tags_text', 'pv.issuer_name'] as $column) {
                    $w->orWhereRaw($column." LIKE ? ESCAPE '!'", [$like]);
                }
            });
        }

        match ($criteria['sort'] ?? 'newest') {
            'priority' => $query->orderByRaw("CASE pv.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")->orderByDesc('challenges.published_at'),
            'title' => $query->orderBy('pv.title'),
            'reviewed' => $query->orderByDesc('challenges.last_reviewed_at'),
            default => $query->orderByDesc('challenges.published_at'),
        };

        return $query->orderByDesc('challenges.id')
            ->with('publishedVersion')
            ->paginate($perPage ?? (int) config('challenges.per_page', 12))
            ->withQueryString();
    }
}
