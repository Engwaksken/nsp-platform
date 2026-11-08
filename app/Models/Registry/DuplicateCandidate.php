<?php

namespace App\Models\Registry;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateCandidate extends Model
{
    protected $table = 'startup_duplicate_candidates';

    protected $fillable = ['startup_a_uuid', 'startup_b_uuid', 'score', 'evidence', 'status', 'decided_by', 'decided_at', 'notes'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'decided_at' => 'datetime'];
    }

    public function startupA(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_a_uuid');
    }

    public function startupB(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_b_uuid');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
