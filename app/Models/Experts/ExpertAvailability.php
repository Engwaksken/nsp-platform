<?php

namespace App\Models\Experts;

use Illuminate\Database\Eloquent\Model;

/** A weekly window (weekday, start, end) in the expert's own IANA time zone. */
class ExpertAvailability extends Model
{
    protected $table = 'expert_availability';

    public const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    protected $fillable = ['expert_user_id', 'weekday', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
