<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableSessionDemand extends Model
{
    protected $fillable = [
        'generation_run_id',
        'course_group_id',
        'session_type',
        'required_sessions_per_week',
        'duration_slots',
        'scheduled_sessions',
        'unscheduled_sessions',
        'status',
        'source',
        'rules',
        'metadata',
    ];

    protected $casts = [
        'rules' => 'array',
        'metadata' => 'array',
    ];

    public function generationRun() { return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id'); }
    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'course_group_id'); }
}
