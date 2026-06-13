<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionConversationEvent extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'event_type', 'title', 'body',
        'occurred_at', 'actor_user_id', 'source_type', 'source_id', 'metadata',
    ];

    protected $casts = ['occurred_at' => 'datetime', 'metadata' => 'array'];

    public function subject() { return $this->morphTo(); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
