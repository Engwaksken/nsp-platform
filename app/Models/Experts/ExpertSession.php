<?php

namespace App\Models\Experts;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpertSession extends Model
{
    use HasUuids;

    protected $table = 'expert_sessions';

    protected $primaryKey = 'uuid';

    public const MODES = ['online' => 'Online', 'in_person' => 'In person', 'phone' => 'Phone'];

    public const STATUSES = ['scheduled' => 'Scheduled', 'completed' => 'Held', 'cancelled' => 'Cancelled'];

    protected $fillable = ['engagement_uuid', 'scheduled_at', 'duration_minutes', 'mode', 'location', 'status', 'created_by'];

    /** Each side's private notes never leave the server in serialised form. */
    protected $hidden = ['expert_notes', 'startup_notes'];

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

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
