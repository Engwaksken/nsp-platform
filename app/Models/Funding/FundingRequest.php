<?php

namespace App\Models\Funding;

use App\Models\Registry\Startup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingRequest extends Model
{
    protected $fillable = ['startup_uuid', 'amount', 'currency', 'instruments', 'pitch', 'use_of_funds', 'status', 'created_by', 'closed_at'];

    protected function casts(): array
    {
        return ['instruments' => 'array', 'amount' => 'integer', 'closed_at' => 'datetime'];
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function amountLabel(): string
    {
        return $this->currency.' '.number_format((float) $this->amount);
    }
}
