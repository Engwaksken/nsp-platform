<?php

namespace App\Models\Capital;

use Illuminate\Database\Eloquent\Model;

/** A founder's or investor's view of whether a suggested match was useful (one per side and pair). */
class MatchFeedback extends Model
{
    public const RATINGS = ['useful' => 'Useful match', 'not_useful' => 'Not a useful match'];

    protected $table = 'investor_match_feedback';

    protected $fillable = ['startup_uuid', 'investor_user_id', 'side', 'given_by', 'rating', 'reason'];
}
