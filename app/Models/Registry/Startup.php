<?php

namespace App\Models\Registry;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** The canonical Hive startup identity (one per real company). */
class Startup extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    public const COUNTRIES = ['UG' => 'Uganda', 'KE' => 'Kenya', 'TZ' => 'Tanzania', 'RW' => 'Rwanda', 'BI' => 'Burundi', 'SS' => 'South Sudan', 'CD' => 'DR Congo', 'ET' => 'Ethiopia', 'NG' => 'Nigeria', 'GH' => 'Ghana', 'ZA' => 'South Africa', 'OTHER' => 'Other'];

    /** Core fields owned by the registry (data-ownership.md). */
    public const CORE_FIELDS = ['legal_name', 'display_name', 'registration_number', 'country', 'year_founded', 'description', 'website'];

    protected $fillable = ['legal_name', 'display_name', 'registration_number', 'country', 'year_founded', 'description', 'website'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'year_founded' => 'integer', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Startup $startup) {
            $startup->normalized_name = self::normalizeName($startup->legal_name.' '.$startup->display_name);
            $startup->normalized_registration = self::normalizeRegistration($startup->registration_number);
            $startup->website_domain = self::domainOf($startup->website);
        });
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function profile(): HasOne
    {
        return $this->hasOne(StartupProfile::class, 'startup_uuid');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(StartupMembership::class, 'startup_uuid');
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active');
    }

    public function founders(): HasMany
    {
        return $this->hasMany(StartupFounder::class, 'startup_uuid')->orderBy('sort_order');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(StartupAlias::class, 'startup_uuid');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(DataSharingConsent::class, 'startup_uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** The active membership of a user, or null. */
    public function membershipOf(?User $user): ?StartupMembership
    {
        if (! $user) {
            return null;
        }

        return $this->memberships()->where('user_id', $user->id)->where('status', 'active')->first();
    }

    /** Lower-case words only, for duplicate checks; never used to merge on its own. */
    public static function normalizeName(?string $name): string
    {
        $name = strtolower((string) $name);
        $name = (string) preg_replace('/\b(ltd|limited|llc|inc|smc|co|company|uganda|u|the)\b/', ' ', $name);
        $words = array_unique(array_filter(explode(' ', (string) preg_replace('/[^a-z0-9]+/', ' ', $name))));
        sort($words);

        return implode(' ', $words);
    }

    public static function normalizeRegistration(?string $number): ?string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $number));

        return $clean === '' ? null : $clean;
    }

    /** Lower-case host without "www.", or null. */
    public static function domainOf(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === '' ? null : preg_replace('/^www\./', '', $host);
    }
}
