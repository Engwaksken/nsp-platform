<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeCategory extends Model
{
    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'sort_order'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(KnowledgeResource::class, 'category_id');
    }

    /** "Parent › Child" label for selects. */
    public function path(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /** This category plus its descendants (taxonomy is shallow, but walk it fully). */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];
        while ($frontier) {
            $frontier = self::query()->whereIn('parent_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /** Categories ordered as a tree (parents followed by their children). */
    public static function tree(): array
    {
        $all = self::query()->with('parent')->orderBy('sort_order')->orderBy('name')->get();
        $out = [];
        $walk = function ($parentId, int $depth) use (&$walk, &$out, $all) {
            foreach ($all->where('parent_id', $parentId) as $category) {
                $category->depth = $depth;
                $out[] = $category;
                $walk($category->id, $depth + 1);
            }
        };
        $walk(null, 0);

        return $out;
    }
}
