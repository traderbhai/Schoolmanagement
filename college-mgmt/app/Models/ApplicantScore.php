<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantScore extends Model
{
    protected $fillable = [
        'applicant_id', 'selection_session_id', 'selection_process_step_id',
        'scored_by', 'parameter_scores', 'total_score', 'max_possible_score',
        'percentage', 'remarks', 'is_final', 'score_status', 'locked_at',
        'locked_by', 'override_reason', 'recommendation',
    ];

    protected $casts = [
        'parameter_scores' => 'array',
        'is_final' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function session()
    {
        return $this->belongsTo(SelectionSession::class, 'selection_session_id');
    }

    public function step()
    {
        return $this->belongsTo(SelectionProcessStep::class, 'selection_process_step_id');
    }

    public function scoredBy()
    {
        return $this->belongsTo(User::class, 'scored_by');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function getGradeAttribute(): string
    {
        return match(true) {
            $this->percentage >= 90 => 'A+',
            $this->percentage >= 80 => 'A',
            $this->percentage >= 70 => 'B',
            $this->percentage >= 60 => 'C',
            $this->percentage >= 50 => 'D',
            default => 'F',
        };
    }
}
