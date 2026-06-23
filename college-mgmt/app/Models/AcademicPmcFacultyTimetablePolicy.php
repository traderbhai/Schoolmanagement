<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcFacultyTimetablePolicy extends Model
{
    protected $fillable = [
        'teacher_id', 'program_id', 'term_id', 'max_consecutive_classes',
        'max_daily_classes', 'requires_lunch_gap', 'allowed_days',
        'unavailable_periods', 'campus_gap_rules', 'status', 'metadata',
    ];

    protected $casts = [
        'requires_lunch_gap' => 'boolean',
        'allowed_days' => 'array',
        'unavailable_periods' => 'array',
        'campus_gap_rules' => 'array',
        'metadata' => 'array',
    ];

    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function term() { return $this->belongsTo(Term::class); }
}
