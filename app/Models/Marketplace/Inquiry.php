<?php

namespace App\Models\Marketplace;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A conversation between someone interested in a listing and the listing startup's team. */
class Inquiry extends Model
{
    use HasUuids;

    protected $table = 'marketplace_inquiries';

    protected $primaryKey = 'uuid';

    protected $fillable = ['listing_uuid', 'startup_uuid', 'inquirer_id', 'inquirer_startup_uuid', 'subject', 'status', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'inquirer_read_at' => 'datetime', 'team_read_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_uuid');
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }

    public function inquirer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inquirer_id');
    }

    public function inquirerStartup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'inquirer_startup_uuid');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'inquiry_uuid')->orderBy('id');
    }

    public function unreadFor(string $side): bool
    {
        return $this->last_message_at && ($side === 'team' ? $this->team_read_at : $this->inquirer_read_at) === null;
    }
}
