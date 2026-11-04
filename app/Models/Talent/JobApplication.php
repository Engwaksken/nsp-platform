<?php

namespace App\Models\Talent;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class JobApplication extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    /** What the startup team sees and sets. */
    public const STATUSES = ['submitted' => 'New', 'shortlisted' => 'Shortlisted', 'interview' => 'Interview', 'offer' => 'Offer made',
        'hired' => 'Hired', 'rejected' => 'Not selected', 'withdrawn' => 'Withdrawn by candidate'];

    /** What the candidate sees. Internal stages are not hidden, just worded for the candidate. */
    public const CANDIDATE_LABELS = ['submitted' => 'Received', 'shortlisted' => 'Shortlisted', 'interview' => 'Interview stage',
        'offer' => 'Offer made', 'hired' => 'Hired', 'rejected' => 'Not selected this time', 'withdrawn' => 'You withdrew'];

    public const CLOSED = ['hired', 'rejected', 'withdrawn'];

    protected $fillable = ['vacancy_uuid', 'startup_uuid', 'applicant_id', 'cover_note', 'cv_path', 'cv_name', 'status', 'invited', 'status_changed_at'];

    protected $hidden = ['team_notes', 'cv_path'];

    protected function casts(): array
    {
        return ['status_changed_at' => 'datetime', 'cv_purged_at' => 'datetime', 'invited' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (JobApplication $application) => $application->cv_path && Storage::disk('local')->delete($application->cv_path));
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class, 'vacancy_uuid');
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'application_uuid')->orderBy('scheduled_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PipelineEvent::class, 'application_uuid')->orderBy('id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED, true);
    }
}
