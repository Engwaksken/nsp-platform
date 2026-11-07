<?php

namespace App\Models\Capital;

use App\Models\Registry\Startup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The investor's current stage for one startup. Private to the investor. */
class PipelineItem extends Model
{
    protected $table = 'investment_pipeline_items';

    protected $fillable = ['investor_user_id', 'startup_uuid', 'stage', 'stage_changed_at'];

    protected function casts(): array
    {
        return ['stage_changed_at' => 'datetime'];
    }

    /** @return array<string, string> */
    public static function stages(): array
    {
        return config('capital.pipeline_stages', []);
    }

    public function stageLabel(): string
    {
        return self::stages()[$this->stage] ?? $this->stage;
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PipelineEvent::class, 'pipeline_item_id');
    }
}
