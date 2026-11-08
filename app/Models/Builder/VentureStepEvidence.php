<?php

namespace App\Models\Builder;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A private evidence file or a source reference (link) attached to a step. */
class VentureStepEvidence extends Model
{
    protected $table = 'venture_step_evidence';

    protected $fillable = ['submission_id', 'startup_uuid', 'kind', 'title', 'disk', 'path', 'original_name', 'mime_type',
        'size', 'url', 'source_note', 'uploaded_by'];

    protected static function booted(): void
    {
        static::deleted(function (self $evidence) {
            if ($evidence->kind === 'file' && $evidence->path) {
                Storage::disk($evidence->disk ?: 'local')->delete($evidence->path);
            }
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(VentureStepSubmission::class, 'submission_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
