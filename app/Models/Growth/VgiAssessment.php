<?php

namespace App\Models\Growth;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VgiAssessment extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['startup_uuid', 'framework_id', 'status', 'started_by'];

    protected function casts(): array
    {
        return [
            'result' => 'array', 'submitted_at' => 'datetime', 'override_at' => 'datetime',
            'score' => 'float', 'override_score' => 'float',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(VgiFramework::class, 'framework_id');
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(VgiResponse::class, 'assessment_uuid');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function overrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    /** The score to show: a staff adjustment if there is one, otherwise the calculated score. */
    public function displayScore(): ?float
    {
        return $this->override_score ?? $this->score;
    }

    public function displayBand(): ?string
    {
        return $this->override_band_label ?? $this->band_label;
    }

    /** @return array<string, string> question key => option key */
    public function answers(): array
    {
        return $this->responses->pluck('option_key', 'question_key')->filter()->all();
    }
}
