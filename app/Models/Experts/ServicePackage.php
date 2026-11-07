<?php

namespace App\Models\Experts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A service package an expert offers: length and either a price or pro bono. */
class ServicePackage extends Model
{
    protected $table = 'expert_services';

    protected $fillable = ['expert_user_id', 'title', 'description', 'duration_minutes', 'price_minor', 'currency', 'is_pro_bono', 'is_active'];

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer', 'price_minor' => 'integer', 'is_pro_bono' => 'boolean', 'is_active' => 'boolean'];
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expert_user_id');
    }

    public function isPaid(): bool
    {
        return ! $this->is_pro_bono && (int) $this->price_minor > 0 && $this->currency;
    }

    public function priceLabel(): string
    {
        return $this->isPaid() ? Money::format((int) $this->price_minor, (string) $this->currency) : 'Pro bono (free)';
    }
}
