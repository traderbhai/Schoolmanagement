<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableGenerationItem extends Model
{
    protected $fillable = ['generation_run_id', 'timetable_version_id', 'course_group_id', 'program_id', 'batch_id', 'term_id', 'subject_id', 'session_demand_id', 'session_index', 'session_type', 'duration_slots', 'teacher_id', 'classroom_id', 'day_of_week', 'timetable_slot_id', 'operational_timetable_entry_id', 'status', 'official_status', 'source_type', 'published_at', 'published_by', 'is_locked', 'confidence', 'explanation', 'conflicts', 'metadata'];
    protected $casts = ['is_locked' => 'boolean', 'published_at' => 'datetime', 'conflicts' => 'array', 'metadata' => 'array'];
    public function generationRun() { return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id'); }
    public function timetableVersion() { return $this->belongsTo(TimetableVersion::class, 'timetable_version_id'); }
    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'course_group_id'); }
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function sessionDemand() { return $this->belongsTo(AcademicPmcTimetableSessionDemand::class, 'session_demand_id'); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function slot() { return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id'); }
    public function operationalTimetableEntry() { return $this->belongsTo(TimetableEntry::class, 'operational_timetable_entry_id'); }

    public function getDayNameAttribute(): string
    {
        return ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$this->day_of_week] ?? '';
    }
}
