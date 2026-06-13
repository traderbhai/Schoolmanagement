<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionEvaluatorScore extends Model
{
    protected $fillable = [
        'assignment_id', 'rubric_id', 'criterion_id', 'evaluator_user_id',
        'score', 'max_score', 'weighted_score', 'comment', 'status',
        'submitted_at', 'locked_at', 'metadata',
    ];

    protected $casts = [
        'score' => 'float',
        'max_score' => 'float',
        'weighted_score' => 'float',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function assignment() { return $this->belongsTo(AdmissionAssessmentPanelAssignment::class, 'assignment_id'); }
    public function rubric() { return $this->belongsTo(AdmissionAssessmentRubric::class, 'rubric_id'); }
    public function criterion() { return $this->belongsTo(AdmissionAssessmentRubricCriterion::class, 'criterion_id'); }
    public function evaluator() { return $this->belongsTo(User::class, 'evaluator_user_id'); }
}
