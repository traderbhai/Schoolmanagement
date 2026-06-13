<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionParentJourney extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'guardian_name', 'guardian_phone', 'guardian_email', 'preferred_channel', 'decision_status', 'next_action', 'next_due_at', 'owner_user_id', 'metadata'];
    protected $casts = ['next_due_at' => 'datetime', 'metadata' => 'array'];
    public function subject() { return $this->morphTo(); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
}
