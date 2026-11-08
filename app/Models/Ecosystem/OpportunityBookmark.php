<?php

namespace App\Models\Ecosystem;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityBookmark extends Model
{
    protected $fillable = ['opportunity_id', 'user_id', 'reminded_at'];

    protected function casts(): array
    {
        return ['reminded_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
