<?php

namespace App\Models\Capital;

use App\Models\Registry\Startup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A startup an investor is keeping an eye on. Private to the investor. */
class WatchlistEntry extends Model
{
    protected $table = 'investor_watchlist';

    protected $fillable = ['investor_user_id', 'startup_uuid'];

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class, 'startup_uuid');
    }
}
