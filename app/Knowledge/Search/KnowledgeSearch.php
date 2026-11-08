<?php

namespace App\Knowledge\Search;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Searches the Knowledge Hub. Every implementation must restrict to live resources the
 * user may open BEFORE ranking, so restricted documents can never be returned.
 *
 * Filters: type, category (id incl. descendants), audience, stage, tag (slug).
 */
interface KnowledgeSearch
{
    public function search(?User $user, string $query, array $filters = [], int $perPage = 12): LengthAwarePaginator;

    /** Engine that produced the last result set ("keyword" or "semantic"). */
    public function engine(): string;
}
