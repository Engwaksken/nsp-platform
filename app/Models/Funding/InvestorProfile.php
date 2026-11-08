<?php

namespace App\Models\Funding;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestorProfile extends Model
{
    public const TYPES = [
        'angel' => 'Angel investor', 'vc' => 'Venture capital fund', 'impact' => 'Impact fund', 'bank' => 'Bank or lender',
        'dfi' => 'Development finance institution', 'corporate' => 'Corporate investor', 'grant_maker' => 'Grant-maker or foundation', 'other' => 'Other',
    ];

    public const INSTRUMENTS = [
        'equity' => 'Equity', 'convertible' => 'Convertible note or SAFE', 'debt' => 'Loan or debt',
        'revenue_share' => 'Revenue-based financing', 'grant' => 'Grant',
    ];

    public const CURRENCIES = ['USD', 'UGX', 'EUR', 'KES', 'GBP'];

    protected $fillable = ['organisation', 'investor_type', 'bio', 'website', 'thesis', 'ticket_min', 'ticket_max', 'currency',
        'sectors', 'stages', 'countries', 'instruments', 'is_listed'];

    protected function casts(): array
    {
        return [
            'sectors' => 'array', 'stages' => 'array', 'countries' => 'array', 'instruments' => 'array',
            'is_listed' => 'boolean', 'verified_at' => 'datetime', 'ticket_min' => 'integer', 'ticket_max' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', 'verified')->whereHas('user', fn ($q) => $q->where('is_active', true));
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified' && $this->user?->is_active;
    }

    public function ticketLabel(): string
    {
        if (! $this->ticket_min && ! $this->ticket_max) {
            return 'Not stated';
        }

        return $this->currency.' '.number_format((float) $this->ticket_min).' – '.number_format((float) $this->ticket_max);
    }
}
