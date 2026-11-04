<?php

namespace App\Models\Experts;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A consultation request or a mentorship between one expert and one startup. */
class Engagement extends Model
{
    use HasUuids;

    protected $table = 'expert_engagements';

    protected $primaryKey = 'uuid';

    public const TYPES = ['consultation' => 'Consultation', 'mentorship' => 'Mentorship'];

    public const STATUSES = ['requested' => 'Waiting for the expert', 'active' => 'Active', 'declined' => 'Declined',
        'cancelled' => 'Cancelled', 'completed' => 'Completed'];

    protected $fillable = ['startup_uuid', 'expert_user_id', 'type', 'source', 'requested_by', 'topic', 'details', 'preferred_times',
        'status', 'response_note', 'decided_by', 'decided_at', 'completed_at', 'outcome'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expert_user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExpertSession::class, 'engagement_uuid')->orderBy('scheduled_at');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SessionAction::class, 'engagement_uuid')->orderByRaw('done_at is not null')->orderBy('due_on');
    }

    public function review(): HasOne
    {
        return $this->hasOne(ExpertReview::class, 'engagement_uuid');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['requested', 'active'], true);
    }
}
