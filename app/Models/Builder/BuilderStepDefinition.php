<?php

namespace App\Models\Builder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of a curriculum version. `fields` is a list of
 * {key, label, type, required, help?, options?}; `checklist` a list of {key, label}.
 * `prerequisites` null means "the previous step in the sequence".
 */
class BuilderStepDefinition extends Model
{
    protected $fillable = ['curriculum_id', 'module_id', 'position', 'key', 'title', 'guidance', 'fields', 'checklist',
        'evidence_requirement', 'requires_review', 'output_template', 'vgi_dimension_keys', 'prerequisites', 'task_suggestions', 'is_active'];

    protected function casts(): array
    {
        return [
            'fields' => 'array', 'checklist' => 'array', 'vgi_dimension_keys' => 'array', 'prerequisites' => 'array',
            'task_suggestions' => 'array', 'requires_review' => 'boolean', 'is_active' => 'boolean', 'position' => 'integer',
        ];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(BuilderCurriculum::class, 'curriculum_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(BuilderModule::class, 'module_id');
    }

    /** @return array<string, string> field key => label */
    public function fieldLabels(): array
    {
        return collect($this->fields ?? [])->mapWithKeys(fn ($f) => [$f['key'] => $f['label'] ?? $f['key']])->all();
    }

    /** @return array<string, string> dimension key => owner label */
    public function vgiDimensions(): array
    {
        $labels = config('builder.vgi_dimensions');

        return collect($this->vgi_dimension_keys ?? [])->mapWithKeys(fn ($k) => [$k => $labels[$k] ?? $k])->all();
    }
}
