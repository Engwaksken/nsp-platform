<?php

namespace App\Models\Builder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuilderModule extends Model
{
    protected $fillable = ['curriculum_id', 'position', 'key', 'title', 'description', 'outputs', 'is_active'];

    protected function casts(): array
    {
        return ['outputs' => 'array', 'is_active' => 'boolean', 'position' => 'integer'];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(BuilderCurriculum::class, 'curriculum_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(BuilderStepDefinition::class, 'module_id')->orderBy('position');
    }
}
