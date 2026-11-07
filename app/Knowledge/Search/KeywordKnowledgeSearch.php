<?php

namespace App\Knowledge\Search;

use App\Models\Knowledge\KnowledgeCategory;
use App\Models\Knowledge\KnowledgeResource;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Database keyword search (LIKE) over title, summary, body and tags. */
class KeywordKnowledgeSearch implements KnowledgeSearch
{
    public function search(?User $user, string $query, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $builder = $this->candidates($user, $filters);
        $query = trim($query);
        if ($query !== '') {
            $terms = array_slice(array_filter(preg_split('/\s+/', mb_substr($query, 0, 100)) ?: []), 0, 8);
            foreach ($terms as $term) {
                $like = '%'.$term.'%';
                $builder->where(fn (Builder $q) => $q->where('title', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('body', 'like', $like)
                    ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $like)));
            }
            // Title matches first.
            $builder->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', ['%'.$query.'%']);
        }

        return $builder->orderByDesc('published_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function engine(): string
    {
        return 'keyword';
    }

    /** Live resources the user may open, narrowed by filters. Shared by every search implementation. */
    public function candidates(?User $user, array $filters = []): Builder
    {
        $builder = KnowledgeResource::query()->live()->visibleTo($user)->with('category', 'tags');

        if (! empty($filters['type'])) {
            $builder->where('resource_type', $filters['type']);
        }
        if (! empty($filters['category'])) {
            $category = KnowledgeCategory::query()->find((int) $filters['category']);
            $builder->whereIn('category_id', $category ? $category->selfAndDescendantIds() : [0]);
        }
        foreach (['audience' => 'audience', 'stage' => 'stages'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $builder->where($column, 'like', '%"'.(string) $filters[$filter].'"%');
            }
        }
        if (! empty($filters['tag'])) {
            $builder->whereHas('tags', fn (Builder $t) => $t->where('slug', (string) $filters['tag']));
        }

        return $builder;
    }
}
