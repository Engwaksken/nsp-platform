<?php

namespace App\Models\Growth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only record of a staff adjustment to an assessment result. Rows are never changed or deleted. */
class VgiOverrideLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['startup_uuid', 'assessment_uuid', 'framework_id', 'original_score', 'original_band_label',
        'previous_score', 'previous_band_label', 'new_score', 'new_band_label', 'reason', 'actor_role', 'actor_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'original_score' => 'float', 'previous_score' => 'float', 'new_score' => 'float'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('VGI override logs are append-only.'));
        static::deleting(fn () => throw new LogicException('VGI override logs are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
