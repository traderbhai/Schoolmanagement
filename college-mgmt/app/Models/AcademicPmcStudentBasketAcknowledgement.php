<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcStudentBasketAcknowledgement extends Model
{
    protected $fillable = [
        'student_id',
        'student_course_allocation_id',
        'timetable_version_id',
        'generation_run_id',
        'acknowledgement_type',
        'status',
        'reason',
        'student_note',
        'pmc_note',
        'submitted_at',
        'decided_by',
        'decided_at',
        'metadata',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function student() { return $this->belongsTo(Student::class); }
    public function allocation() { return $this->belongsTo(AcademicPmcStudentCourseAllocation::class, 'student_course_allocation_id'); }
    public function timetableVersion() { return $this->belongsTo(TimetableVersion::class); }
    public function generationRun() { return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
