<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcFacultyAssignmentAcknowledgement extends Model
{
    protected $fillable = [
        'group_faculty_assignment_id', 'teacher_id', 'status', 'response_type',
        'faculty_note', 'constraints_raised', 'requested_by', 'requested_at',
        'responded_by', 'responded_at', 'reviewed_by', 'reviewed_at',
        'review_note', 'metadata',
    ];

    protected $casts = [
        'constraints_raised' => 'array',
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function assignment() { return $this->belongsTo(AcademicPmcGroupFacultyAssignment::class, 'group_faculty_assignment_id'); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function responder() { return $this->belongsTo(User::class, 'responded_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
