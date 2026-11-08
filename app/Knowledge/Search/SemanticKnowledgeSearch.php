<?php

namespace App\Knowledge\Search;

use App\Models\Knowledge\KnowledgeResource;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Pagination\Paginator as BasePaginator;
use Throwable;

/**
 * Adapter for an optional semantic index. Disabled by default; when it is disabled, not
 * configured, unreachable or failing, it falls back to keyword search.
 *
 * Visibility is enforced before ranking (only visible candidate ids are sent to the ranker)
 * and again after ranking (anything outside the candidate set is dropped).
 */
class SemanticKnowledgeSearch implements KnowledgeSearch
{
    private string $engine = 'semantic';

    public function __construct(private KeywordKnowledgeSearch $keyword, private ?SemanticRanker $ranker = null) {}

    public function available(): bool
    {
        if (! config('knowledge.semantic.enabled') || ! $this->ranker) {
            return false;
        }
        try {
            return $this->ranker->available();
        } catch (Throwable) {
            return false;
        }
    }

    public function search(?User $user, string $query, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        if (trim($query) === '' || ! $this->available()) {
            return $this->fallback($user, $query, $filters, $perPage);
        }

        $candidateIds = $this->keyword->candidates($user, $filters)->pluck('id')->map(fn ($id) => (int) $id)->all();
        try {
            $ranked = $this->ranker->rank($query, $candidateIds);
        } catch (Throwable) {
            return $this->fallback($user, $query, $filters, $perPage);
        }
        $allowed = array_flip($candidateIds);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ranked), fn (int $id) => isset($allowed[$id]))));

        $this->engine = 'semantic';
        $page = max(1, BasePaginator::resolveCurrentPage());
        $slice = array_slice($ids, ($page - 1) * $perPage, $perPage);
        // Re-check visibility on the final rows (defence in depth).
        $rows = KnowledgeResource::query()->live()->visibleTo($user)->with('category', 'tags')->whereIn('id', $slice ?: [0])->get()
            ->sortBy(fn ($r) => array_search($r->id, $slice, true))->values();

        return (new Paginator($rows, count($ids), $perPage, $page, ['path' => BasePaginator::resolveCurrentPath()]))->withQueryString();
    }

    public function engine(): string
    {
        return $this->engine;
    }

    private function fallback(?User $user, string $query, array $filters, int $perPage): LengthAwarePaginator
    {
        $this->engine = 'keyword';

        return $this->keyword->search($user, $query, $filters, $perPage);
    }
}
