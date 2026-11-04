<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Draft / published / archived content. A published item with a future
 * published_at is "scheduled" and stays hidden until that time.
 */
trait Publishable
{
    public const STATUSES = ['draft', 'published', 'archived'];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function displayStatus(): string
    {
        if ($this->status === 'published' && $this->published_at?->isFuture()) {
            return 'scheduled';
        }

        return $this->status;
    }
}
