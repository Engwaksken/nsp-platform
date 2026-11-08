<?php

namespace App\Models\Growth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthUpdate extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['startup_uuid', 'growth_action_id', 'user_id', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
