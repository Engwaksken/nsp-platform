<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class KnowledgeTag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeResource::class, 'knowledge_resource_tag');
    }

    /** Turns "Fundraising, legal, Pitch decks" into tag ids, creating missing tags. */
    public static function idsFromString(?string $input): array
    {
        $ids = [];
        foreach (array_unique(array_filter(array_map('trim', explode(',', (string) $input)))) as $name) {
            $name = mb_substr($name, 0, 60);
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }
            $ids[] = self::query()->firstOrCreate(['slug' => mb_substr($slug, 0, 60)], ['name' => $name])->id;
        }

        return array_values(array_unique($ids));
    }
}
