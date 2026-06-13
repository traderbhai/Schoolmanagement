<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAssessmentNormalizedScore extends Model
{
    protected $fillable = ['assignment_id', 'panel_id', 'applicant_id', 'evaluator_user_id', 'raw_score', 'normalized_score', 'evaluator_mean', 'panel_mean', 'outlier_flag', 'review_status', 'metadata'];
    protected $casts = ['raw_score' => 'float', 'normalized_score' => 'float', 'evaluator_mean' => 'float', 'panel_mean' => 'float', 'outlier_flag' => 'boolean', 'metadata' => 'array'];
    public function assignment() { return $this->belongsTo(AdmissionAssessmentPanelAssignment::class, 'assignment_id'); }
    public function panel() { return $this->belongsTo(AdmissionAssessmentPanel::class, 'panel_id'); }
    public function applicant() { return $this->belongsTo(Applicant::class); }
    public function evaluator() { return $this->belongsTo(User::class, 'evaluator_user_id'); }
}
