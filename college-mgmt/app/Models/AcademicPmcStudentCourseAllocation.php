<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcStudentCourseAllocation extends Model
{
    protected $fillable = ['allocation_batch_id', 'student_subject_enrollment_id', 'student_id', 'subject_id', 'term_id', 'allocation_type', 'allocation_source', 'approval_status', 'basket_status', 'priority_rank', 'waitlisted', 'override_reason', 'validation_flags', 'metadata'];
    protected $casts = ['waitlisted' => 'boolean', 'validation_flags' => 'array', 'metadata' => 'array'];
    public function allocationBatch() { return $this->belongsTo(AcademicPmcCourseAllocationBatch::class, 'allocation_batch_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function term() { return $this->belongsTo(Term::class); }
}
