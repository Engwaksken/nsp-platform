<?php

namespace App\Models\Builder;

use App\Models\Registry\Startup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A startup working through one pinned curriculum version. */
class VentureBuilderEnrollment extends Model
{
    protected $fillable = ['startup_uuid', 'curriculum_id', 'status', 'migrated_from_id', 'started_by', 'started_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime'];
    }

    public static function activeFor(Startup $startup): ?self
    {
        return self::query()->where('startup_uuid', $startup->uuid)->where('status', 'active')->latest('id')->first();
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(BuilderCurriculum::class, 'curriculum_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(VentureStepSubmission::class, 'enrollment_id');
    }
}
