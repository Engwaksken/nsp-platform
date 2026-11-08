<?php

namespace App\Models\Growth;

use Illuminate\Database\Eloquent\Model;

class VgiResponse extends Model
{
    protected $fillable = ['assessment_uuid', 'question_key', 'option_key', 'evidence_note', 'answered_by'];
}
