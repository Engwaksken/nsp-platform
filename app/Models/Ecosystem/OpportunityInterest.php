<?php

namespace App\Models\Ecosystem;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A registration for an event or an expression of interest in an opportunity run through NSP. */
class OpportunityInterest extends Model
{
    public const STATUSES = ['registered' => 'Registered', 'confirmed' => 'Confirmed', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn'];

    protected $fillable = ['opportunity_id', 'startup_uuid', 'user_id', 'message', 'status'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }
}
