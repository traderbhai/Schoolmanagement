<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcGroupFacultyAssignment extends Model
{
    protected $fillable = ['course_group_id', 'teacher_id', 'assignment_role', 'assignment_source', 'approval_status', 'weekly_hours', 'is_backup', 'assigned_by', 'notes', 'metadata'];
    protected $casts = ['is_backup' => 'boolean', 'metadata' => 'array'];
    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'course_group_id'); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function acknowledgements() { return $this->hasMany(AcademicPmcFacultyAssignmentAcknowledgement::class, 'group_faculty_assignment_id'); }
}
