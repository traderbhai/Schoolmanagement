<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcCourseAllocationException extends Model
{
    protected $fillable = [
        'student_course_allocation_id', 'student_id', 'subject_id', 'term_id',
        'exception_type', 'status', 'credit_delta', 'requires_dean_approval',
        'reason', 'validation_flags', 'requested_by', 'requested_at',
        'decided_by', 'decided_at', 'decision_note', 'metadata',
    ];

    protected $casts = [
        'requires_dean_approval' => 'boolean',
        'validation_flags' => 'array',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function allocation() { return $this->belongsTo(AcademicPmcStudentCourseAllocation::class, 'student_course_allocation_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
