<?php

namespace App\Models\Capital;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Activity between an investor and a startup. Meta holds labels only, never private content. */
class Interaction extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'investor_interactions';

    protected $fillable = ['startup_uuid', 'investor_user_id', 'actor_user_id', 'type', 'visible_to_startup', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'visible_to_startup' => 'boolean', 'created_at' => 'datetime'];
    }

    public function scopeVisibleToStartup(Builder $query): Builder
    {
        return $query->where('visible_to_startup', true);
    }

    public function typeLabel(): string
    {
        return (string) config('capital.interaction_types.'.$this->type, $this->type);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }
}
