<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableQualityScore extends Model
{
    protected $fillable = ['generation_run_id', 'timetable_version_id', 'overall_score', 'hard_conflicts', 'soft_warnings', 'student_compactness_score', 'faculty_balance_score', 'room_utilization_score', 'details'];
    protected $casts = ['details' => 'array'];
}
