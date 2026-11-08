<?php

namespace App\Models\Experts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Admin-managed expert categories (mentor, consultant, researcher, legal adviser ...). */
class ExpertCategory extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(ExpertProfile::class, 'expert_category_profile');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
