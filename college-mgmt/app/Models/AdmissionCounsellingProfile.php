<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionCounsellingProfile extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'preferred_program_id', 'budget_sensitivity',
        'scholarship_need', 'hostel_interest', 'transport_interest',
        'parent_decision_maker', 'key_objection', 'lost_reason',
        'competitor_considered', 'parent_spoken', 'last_parent_contacted_at',
        'updated_by', 'metadata',
    ];

    protected $casts = [
        'scholarship_need' => 'boolean',
        'hostel_interest' => 'boolean',
        'transport_interest' => 'boolean',
        'parent_spoken' => 'boolean',
        'last_parent_contacted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function subject() { return $this->morphTo(); }
    public function preferredProgram() { return $this->belongsTo(Program::class, 'preferred_program_id'); }
}
