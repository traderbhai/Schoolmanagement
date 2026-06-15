<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableGenerationItem extends Model
{
    protected $fillable = ['generation_run_id', 'course_group_id', 'session_demand_id', 'session_index', 'session_type', 'duration_slots', 'teacher_id', 'classroom_id', 'day_of_week', 'timetable_slot_id', 'operational_timetable_entry_id', 'status', 'is_locked', 'confidence', 'explanation', 'conflicts', 'metadata'];
    protected $casts = ['is_locked' => 'boolean', 'conflicts' => 'array', 'metadata' => 'array'];
    public function generationRun() { return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id'); }
    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'course_group_id'); }
    public function sessionDemand() { return $this->belongsTo(AcademicPmcTimetableSessionDemand::class, 'session_demand_id'); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function slot() { return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id'); }
    public function operationalTimetableEntry() { return $this->belongsTo(TimetableEntry::class, 'operational_timetable_entry_id'); }
}
