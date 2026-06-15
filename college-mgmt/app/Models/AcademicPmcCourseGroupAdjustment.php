<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcCourseGroupAdjustment extends Model
{
    protected $fillable = [
        'course_group_id', 'target_course_group_id', 'student_id',
        'adjustment_type', 'status', 'from_strength', 'to_strength',
        'target_from_strength', 'target_to_strength', 'requires_dean_approval',
        'reason', 'impact_summary', 'requested_by', 'requested_at',
        'decided_by', 'decided_at', 'decision_note', 'metadata',
    ];

    protected $casts = [
        'requires_dean_approval' => 'boolean',
        'impact_summary' => 'array',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class); }
    public function targetCourseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'target_course_group_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
