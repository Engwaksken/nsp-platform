<?php

namespace App\Models\Builder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The output document of a step: generated from the step's template, then editable. Every change is a new version. */
class BuilderDocument extends Model
{
    protected $fillable = ['submission_id', 'startup_uuid', 'title', 'current_version'];

    protected function casts(): array
    {
        return ['current_version' => 'integer'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(VentureStepSubmission::class, 'submission_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BuilderDocumentVersion::class, 'document_id')->orderByDesc('number');
    }

    public function version(?int $number = null): ?BuilderDocumentVersion
    {
        return BuilderDocumentVersion::query()->where('document_id', $this->id)->where('number', $number ?? $this->current_version)->first();
    }
}
