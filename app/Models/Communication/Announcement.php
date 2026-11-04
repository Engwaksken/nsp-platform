<?php

namespace App\Models\Communication;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    public const AUDIENCES = [
        'everyone' => 'Everyone with an active account',
        'founders' => 'Startup team members',
        'investors' => 'Verified investors',
        'experts' => 'Verified experts and mentors',
    ];

    protected $fillable = ['title', 'body', 'url', 'audience', 'created_by'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'recipients' => 'integer'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
