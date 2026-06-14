<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcCourseGroupMember extends Model
{
    protected $fillable = ['course_group_id', 'student_id', 'student_course_allocation_id', 'status', 'move_reason', 'moved_by', 'metadata'];
    protected $casts = ['metadata' => 'array'];
    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'course_group_id'); }
    public function student() { return $this->belongsTo(Student::class); }
}
