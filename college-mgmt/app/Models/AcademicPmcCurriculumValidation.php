<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcCurriculumValidation extends Model
{
    protected $fillable = [
        'curriculum_plan_id', 'program_id', 'batch_id', 'term_id', 'subject_id',
        'validation_type', 'status', 'severity', 'score', 'title', 'details',
        'recommended_action', 'owner_user_id', 'due_at', 'resolved_at',
        'evidence', 'metadata',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'evidence' => 'array',
        'metadata' => 'array',
    ];

    public function curriculumPlan() { return $this->belongsTo(AcademicPmcCurriculumPlan::class, 'curriculum_plan_id'); }
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
}
