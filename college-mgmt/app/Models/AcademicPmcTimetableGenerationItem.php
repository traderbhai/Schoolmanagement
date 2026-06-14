<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableGenerationItem extends Model
{
    protected $fillable = ['generation_run_id', 'course_group_id', 'teacher_id', 'classroom_id', 'day_of_week', 'timetable_slot_id', 'status', 'is_locked', 'confidence', 'explanation', 'conflicts', 'metadata'];
    protected $casts = ['is_locked' => 'boolean', 'conflicts' => 'array', 'metadata' => 'array'];
    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'course_group_id'); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function slot() { return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id'); }
}
