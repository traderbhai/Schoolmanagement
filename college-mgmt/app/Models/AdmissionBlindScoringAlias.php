<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionBlindScoringAlias extends Model
{
    protected $fillable = ['panel_id', 'applicant_id', 'alias_code', 'is_active', 'metadata'];
    protected $casts = ['is_active' => 'boolean', 'metadata' => 'array'];
    public function panel() { return $this->belongsTo(AdmissionAssessmentPanel::class, 'panel_id'); }
    public function applicant() { return $this->belongsTo(Applicant::class); }
}
