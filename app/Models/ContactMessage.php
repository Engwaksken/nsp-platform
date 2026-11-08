<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    public const TOPICS = [
        'general' => 'General enquiry',
        'startup' => 'Startup support',
        'partnership' => 'Partnership',
        'media' => 'Media',
        'privacy' => 'Privacy or data request',
    ];

    public const STATUSES = ['new', 'in_progress', 'closed', 'spam'];

    protected $fillable = ['topic', 'name', 'email', 'subject', 'message', 'consent_given', 'consent_text_version', 'ip_hash', 'status', 'routed_to'];

    protected function casts(): array
    {
        return ['consent_given' => 'boolean'];
    }
}
