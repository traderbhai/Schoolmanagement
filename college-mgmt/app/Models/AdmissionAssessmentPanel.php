<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionAssessmentPanel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'panel_type', 'program_id', 'batch_id', 'selection_session_id',
        'rubric_id', 'capacity', 'venue', 'online_link', 'scheduled_at', 'status',
        'readiness_status', 'created_by', 'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SelectionSession::class, 'selection_session_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(AdmissionAssessmentPanelMember::class, 'panel_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AdmissionAssessmentPanelAssignment::class, 'panel_id');
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(AdmissionAssessmentRubric::class, 'rubric_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(AdmissionAssessmentArtifact::class, 'panel_id');
    }

    public function scheduleConflicts(): HasMany
    {
        return $this->hasMany(AdmissionAssessmentScheduleConflict::class, 'panel_id');
    }
}
