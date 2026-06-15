<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcSessionDeliveryLog extends Model
{
    protected $fillable = [
        'group_delivery_tracker_id', 'generation_item_id', 'course_group_id',
        'subject_id', 'teacher_id', 'classroom_id', 'timetable_slot_id',
        'day_of_week', 'scheduled_date', 'session_status', 'delivery_type',
        'attendance_marked', 'lesson_plan_updated', 'material_uploaded',
        'topic_planned', 'topic_covered', 'gap_reason', 'evidence', 'metadata',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'attendance_marked' => 'boolean',
        'lesson_plan_updated' => 'boolean',
        'material_uploaded' => 'boolean',
        'evidence' => 'array',
        'metadata' => 'array',
    ];

    public function tracker() { return $this->belongsTo(AcademicPmcGroupDeliveryTracker::class, 'group_delivery_tracker_id'); }
    public function generationItem() { return $this->belongsTo(AcademicPmcTimetableGenerationItem::class, 'generation_item_id'); }
    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'course_group_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function slot() { return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id'); }
}
