<?php

namespace App\Models\Talent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    public const MODES = ['online' => 'Online', 'in_person' => 'In person', 'phone' => 'Phone'];

    public const STATUSES = ['proposed' => 'Waiting for the candidate', 'confirmed' => 'Confirmed', 'declined' => 'Candidate asked for another time',
        'done' => 'Held', 'cancelled' => 'Cancelled'];

    protected $fillable = ['application_uuid', 'scheduled_at', 'duration_minutes', 'mode', 'location', 'status', 'candidate_note', 'created_by'];

    protected $hidden = ['team_notes'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'duration_minutes' => 'integer'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'application_uuid');
    }
}
