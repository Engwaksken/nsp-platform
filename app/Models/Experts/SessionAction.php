<?php

namespace App\Models\Experts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAction extends Model
{
    public const OWNERS = ['startup' => 'Startup team', 'expert' => 'Expert'];

    protected $fillable = ['engagement_uuid', 'session_uuid', 'title', 'owner', 'due_on', 'done_at', 'created_by'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime'];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_uuid');
    }

    public function isOverdue(): bool
    {
        return ! $this->done_at && $this->due_on && $this->due_on->isPast() && ! $this->due_on->isToday();
    }
}
