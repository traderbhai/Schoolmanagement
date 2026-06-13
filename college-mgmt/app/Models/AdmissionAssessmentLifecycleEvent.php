<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAssessmentLifecycleEvent extends Model
{
    protected $fillable = [
        'selection_session_id', 'panel_id', 'assignment_id', 'applicant_id',
        'from_status', 'to_status', 'reason', 'notes', 'actor_user_id', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function session() { return $this->belongsTo(SelectionSession::class, 'selection_session_id'); }
    public function panel() { return $this->belongsTo(AdmissionAssessmentPanel::class, 'panel_id'); }
    public function assignment() { return $this->belongsTo(AdmissionAssessmentPanelAssignment::class, 'assignment_id'); }
    public function applicant() { return $this->belongsTo(Applicant::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
