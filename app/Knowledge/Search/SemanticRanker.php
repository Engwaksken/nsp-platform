<?php

namespace App\Knowledge\Search;

/**
 * Port for a future vector/semantic index. None ships with the platform; one must be
 * evaluated and approved before it is configured (config/knowledge.php semantic.ranker).
 */
interface SemanticRanker
{
    /** False when the index or vendor cannot be reached right now. */
    public function available(): bool;

    /**
     * Ranks ONLY the given candidate resource ids (already filtered for visibility) by relevance to the query.
     *
     * @param  array<int>  $candidateIds
     * @return array<int> resource ids, best first
     */
    public function rank(string $query, array $candidateIds): array;
}
