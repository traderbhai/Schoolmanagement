<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionEvaluatorAssignmentBatch extends Model
{
    protected $fillable = ['panel_id', 'strategy', 'fixed_evaluator_id', 'candidate_count', 'assigned_count', 'conflict_count', 'created_by', 'metadata'];
    protected $casts = ['metadata' => 'array'];
    public function panel() { return $this->belongsTo(AdmissionAssessmentPanel::class, 'panel_id'); }
}
