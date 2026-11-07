<?php

namespace App\Models\Experts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One identity, credential or reference check. Documents live on the private disk and only staff can download them. */
class ExpertVerification extends Model
{
    public const TYPES = ['identity' => 'Identity', 'credential' => 'Credential or qualification', 'reference' => 'Professional reference'];

    public const STATUSES = ['pending' => 'Waiting for review', 'verified' => 'Verified', 'rejected' => 'Not accepted'];

    protected $fillable = ['expert_profile_id', 'type', 'title', 'details', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    protected $hidden = ['path', 'disk'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'size' => 'integer'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ExpertProfile::class, 'expert_profile_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
