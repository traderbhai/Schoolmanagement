<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcFacultyPreference extends Model
{
    protected $fillable = ['teacher_id', 'term_id', 'faculty_type', 'available_days', 'preferred_slots', 'unavailable_slots', 'max_classes_per_day', 'max_consecutive_classes', 'max_weekly_load', 'subject_expertise', 'restriction_notes', 'metadata'];
    protected $casts = ['available_days' => 'array', 'preferred_slots' => 'array', 'unavailable_slots' => 'array', 'subject_expertise' => 'array', 'metadata' => 'array'];
    public function teacher() { return $this->belongsTo(Teacher::class); }
}
