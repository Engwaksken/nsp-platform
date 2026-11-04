<?php

namespace App\Models\Registry;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StartupMerge extends Model
{
    public $timestamps = false;

    protected $fillable = ['source_uuid', 'target_uuid', 'candidate_id', 'journal', 'reason', 'merged_by', 'merged_at', 'undone_by', 'undone_at'];

    protected function casts(): array
    {
        return ['journal' => 'array', 'merged_at' => 'datetime', 'undone_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'source_uuid');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'target_uuid');
    }

    public function merger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }
}
