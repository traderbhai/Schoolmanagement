<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAssessmentArtifact extends Model
{
    protected $fillable = [
        'selection_session_id', 'panel_id', 'applicant_id', 'artifact_type',
        'title', 'topic', 'group_number', 'artifact_url', 'prep_minutes',
        'submission_due_at', 'moderator_notes', 'observer_notes', 'metadata',
    ];

    protected $casts = ['submission_due_at' => 'datetime', 'metadata' => 'array'];

    public function session() { return $this->belongsTo(SelectionSession::class, 'selection_session_id'); }
    public function panel() { return $this->belongsTo(AdmissionAssessmentPanel::class, 'panel_id'); }
    public function applicant() { return $this->belongsTo(Applicant::class); }
}
