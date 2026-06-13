<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAssessmentRubric extends Model
{
    protected $fillable = [
        'name', 'assessment_type', 'program_id', 'batch_id', 'version',
        'minimum_score', 'recommendation_options', 'evaluator_instructions',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'recommendation_options' => 'array',
        'is_active' => 'boolean',
        'minimum_score' => 'float',
    ];

    public function criteria() { return $this->hasMany(AdmissionAssessmentRubricCriterion::class, 'rubric_id')->orderBy('sort_order'); }
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
