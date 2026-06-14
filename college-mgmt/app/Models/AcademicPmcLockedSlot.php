<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcLockedSlot extends Model
{
    protected $fillable = ['title', 'slot_type', 'program_id', 'batch_id', 'term_id', 'course_group_id', 'teacher_id', 'classroom_id', 'day_of_week', 'timetable_slot_id', 'is_hard_lock', 'status', 'created_by', 'reason', 'metadata'];
    protected $casts = ['is_hard_lock' => 'boolean', 'metadata' => 'array'];
    public function slot() { return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id'); }
    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'course_group_id'); }
}
