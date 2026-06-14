<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcCourseGroup extends Model
{
    protected $fillable = ['name', 'group_type', 'program_id', 'batch_id', 'term_id', 'subject_id', 'owner_user_id', 'min_capacity', 'max_capacity', 'current_strength', 'status', 'is_locked', 'constraints', 'metadata'];
    protected $casts = ['is_locked' => 'boolean', 'constraints' => 'array', 'metadata' => 'array'];
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function members() { return $this->hasMany(AcademicPmcCourseGroupMember::class, 'course_group_id'); }
    public function facultyAssignments() { return $this->hasMany(AcademicPmcGroupFacultyAssignment::class, 'course_group_id'); }
}
