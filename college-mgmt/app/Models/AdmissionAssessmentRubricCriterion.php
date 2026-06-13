<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAssessmentRubricCriterion extends Model
{
    protected $fillable = [
        'rubric_id', 'name', 'description', 'max_score', 'weight',
        'requires_comment', 'sort_order',
    ];

    protected $casts = [
        'max_score' => 'float',
        'weight' => 'float',
        'requires_comment' => 'boolean',
    ];

    public function rubric() { return $this->belongsTo(AdmissionAssessmentRubric::class, 'rubric_id'); }
}
