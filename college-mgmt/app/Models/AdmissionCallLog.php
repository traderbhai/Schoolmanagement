<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionCallLog extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'caller_user_id', 'phone', 'disposition',
        'outcome_reason', 'duration_seconds', 'called_at', 'next_followup_at',
        'notes', 'metadata',
    ];

    protected $casts = ['called_at' => 'datetime', 'next_followup_at' => 'datetime', 'metadata' => 'array'];

    public function subject() { return $this->morphTo(); }
    public function caller() { return $this->belongsTo(User::class, 'caller_user_id'); }
}
