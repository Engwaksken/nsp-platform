<?php

namespace App\Models\Capital;

use App\Models\Registry\Startup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Funding extras for one startup. Traction and financial text are encrypted and shared only through a scope. */
class FundingProfile extends Model
{
    protected $table = 'startup_funding_profiles';

    protected $fillable = ['startup_uuid', 'round_stage', 'instruments_sought', 'use_of_funds_breakdown', 'traction_summary', 'financial_summary', 'updated_by'];

    protected $hidden = ['traction_summary', 'financial_summary'];

    protected function casts(): array
    {
        return [
            'instruments_sought' => 'array', 'use_of_funds_breakdown' => 'array',
            'traction_summary' => 'encrypted', 'financial_summary' => 'encrypted',
        ];
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function roundLabel(): ?string
    {
        return $this->round_stage ? config('capital.round_stages.'.$this->round_stage, $this->round_stage) : null;
    }
}
