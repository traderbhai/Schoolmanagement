<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcFacultyAvailabilityRequest extends Model
{
    protected $fillable = [
        'teacher_id', 'term_id', 'request_type', 'status', 'available_days',
        'preferred_slots', 'unavailable_slots', 'max_classes_per_day',
        'max_consecutive_classes', 'max_weekly_load', 'reason', 'submitted_by',
        'submitted_at', 'decided_by', 'decided_at', 'decision_note', 'metadata',
    ];

    protected $casts = [
        'available_days' => 'array',
        'preferred_slots' => 'array',
        'unavailable_slots' => 'array',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
