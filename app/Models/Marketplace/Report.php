<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A report about a listing or a conversation, handled by Hive staff as a dispute. */
class Report extends Model
{
    protected $table = 'marketplace_reports';

    public const REASONS = ['misleading' => 'Misleading or false', 'scam' => 'Looks like a scam or fraud', 'offensive' => 'Offensive or abusive',
        'not_delivered' => 'Paid or agreed but not delivered', 'other' => 'Something else'];

    public const STATUSES = ['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];

    protected $fillable = ['listing_uuid', 'inquiry_uuid', 'reporter_id', 'reason', 'details'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_uuid');
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class, 'inquiry_uuid');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
