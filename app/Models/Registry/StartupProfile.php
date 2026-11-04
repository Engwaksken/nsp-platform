<?php

namespace App\Models\Registry;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StartupProfile extends Model
{
    protected $primaryKey = 'startup_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    public const SECTORS = ['Agriculture & Agritech', 'Health', 'Education', 'Fintech', 'Energy & Climate', 'Manufacturing', 'Creative & Media', 'E-commerce & Retail', 'Logistics & Mobility', 'Tourism & Hospitality', 'ICT & Software', 'Water & Sanitation', 'Other'];

    public const REVENUE_BANDS = ['pre_revenue' => 'Pre-revenue', 'under_10m' => 'Under UGX 10M a year', '10m_50m' => 'UGX 10M – 50M', '50m_200m' => 'UGX 50M – 200M', '200m_1b' => 'UGX 200M – 1B', 'over_1b' => 'Over UGX 1B'];

    /** Who may see each sensitive group. Defaults keep money private to the startup's editors. */
    public const VISIBILITY_GROUPS = [
        'financials' => ['label' => 'Revenue and funding', 'fields' => ['revenue_band', 'funding_raised_amount', 'funding_currency'], 'default' => 'editors'],
        'traction' => ['label' => 'Traction summary', 'fields' => ['traction_summary'], 'default' => 'members'],
        'contact' => ['label' => 'Contact email and phone', 'fields' => ['contact_email', 'contact_phone'], 'default' => 'members'],
    ];

    public const VISIBILITY_LEVELS = [
        'editors' => 'Only owners, founders and admins of this startup',
        'members' => 'Everyone on this startup’s team',
        'ecosystem' => 'Our team and Hive staff',
    ];

    protected $fillable = ['sector', 'sub_sector', 'headquarters_location', 'team_size', 'revenue_band', 'traction_summary',
        'funding_raised_amount', 'funding_currency', 'contact_email', 'contact_phone', 'visibility'];

    protected function casts(): array
    {
        return [
            'revenue_band' => 'encrypted',
            'traction_summary' => 'encrypted',
            'funding_raised_amount' => 'encrypted',
            'contact_phone' => 'encrypted',
            'visibility' => 'array',
            'team_size' => 'integer',
        ];
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function visibilityOf(string $group): string
    {
        return $this->visibility[$group] ?? self::VISIBILITY_GROUPS[$group]['default'];
    }

    /** Percentage of profile fields filled in, shown as a completion meter. */
    public function completion(Startup $startup): int
    {
        $values = [
            $startup->legal_name, $startup->display_name, $startup->description, $startup->website, $startup->year_founded,
            $startup->registration_number, $this->sector, $this->headquarters_location, $this->team_size,
            $this->revenue_band, $this->traction_summary, $this->contact_email,
        ];
        $filled = count(array_filter($values, fn ($value) => $value !== null && $value !== ''));

        return (int) round($filled / count($values) * 100);
    }
}
