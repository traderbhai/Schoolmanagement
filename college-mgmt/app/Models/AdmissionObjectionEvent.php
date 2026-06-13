<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionObjectionEvent extends Model
{
    protected $fillable = ['objection_type_id', 'subject_type', 'subject_id', 'counsellor_user_id', 'stage', 'status', 'notes'];
    public function subject() { return $this->morphTo(); }
    public function type() { return $this->belongsTo(AdmissionObjectionType::class, 'objection_type_id'); }
}
