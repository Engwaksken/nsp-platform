<?php

namespace App\Models\Integration;

use Illuminate\Database\Eloquent\Model;

/** A startup's application or program in IMS, as summarised by IMS events. No scores or reviewer notes. */
class ProgramRecord extends Model
{
    protected $table = 'startup_program_records';

    protected $fillable = ['startup_uuid', 'source_system', 'external_application_id', 'program_title', 'cohort_name', 'status',
        'submitted_at', 'status_changed_at', 'program_start_date', 'program_end_date', 'enrolled'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime', 'status_changed_at' => 'datetime',
            'program_start_date' => 'date', 'program_end_date' => 'date', 'enrolled' => 'boolean',
        ];
    }
}
