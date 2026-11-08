<?php

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One assistant request: counts only, never the text. */
class AiInteraction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'startup_uuid', 'task', 'model', 'status', 'input_chars', 'output_chars', 'input_tokens', 'output_tokens', 'error'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
