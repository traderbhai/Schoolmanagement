<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionAssessmentPanelAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'panel_id', 'selection_session_id', 'applicant_id', 'evaluator_user_id',
        'attendance_status', 'score_status', 'recommendation', 'manager_override_reason',
        'overridden_by', 'score_locked_at', 'finalized_at', 'metadata',
    ];

    protected $casts = [
        'score_locked_at' => 'datetime',
        'finalized_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function panel(): BelongsTo
    {
        return $this->belongsTo(AdmissionAssessmentPanel::class, 'panel_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SelectionSession::class, 'selection_session_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id');
    }
}
