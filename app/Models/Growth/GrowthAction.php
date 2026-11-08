<?php

namespace App\Models\Growth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthAction extends Model
{
    public const STATUSES = ['todo' => 'To do', 'doing' => 'In progress', 'done' => 'Done'];

    protected $fillable = ['startup_uuid', 'assessment_uuid', 'dimension_key', 'question_key', 'title', 'details',
        'owner_user_id', 'due_date', 'status', 'completed_at', 'created_by'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(GrowthUpdate::class)->latest('id');
    }
}
