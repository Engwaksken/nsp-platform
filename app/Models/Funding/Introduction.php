<?php

namespace App\Models\Funding;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Introduction extends Model
{
    use HasUuids;

    protected $table = 'investor_introductions';

    protected $primaryKey = 'uuid';

    public const STATUSES = ['requested' => 'Waiting for a reply', 'accepted' => 'Connected', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn'];

    /** The investor's private pipeline stages. */
    public const STAGES = ['interested' => 'Interested', 'meeting' => 'Meeting', 'due_diligence' => 'Due diligence',
        'term_sheet' => 'Term sheet', 'invested' => 'Invested', 'passed' => 'Passed'];

    protected $fillable = ['startup_uuid', 'investor_user_id', 'requested_by', 'requester_user_id', 'message', 'status', 'decided_by', 'decided_at'];

    protected $hidden = ['investor_stage', 'investor_notes'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
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

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }
}
