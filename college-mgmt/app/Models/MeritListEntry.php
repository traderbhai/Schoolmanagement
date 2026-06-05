<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeritListEntry extends Model
{
    protected $fillable = [
        'program_id', 'batch_id', 'applicant_id', 'rank',
        'total_weighted_score', 'step_scores', 'academic_score', 'composite_score',
        'merit_list_version', 'decision', 'decided_by', 'decided_at', 'notes',
        'category', 'category_rank', 'quota_type', 'is_supernumerary',
    ];

    protected $casts = [
        'step_scores' => 'array',
        'decided_at'  => 'datetime',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function getDecisionBadgeAttribute(): string
    {
        return match($this->decision) {
            'selected'   => 'badge bg-success',
            'waitlisted' => 'badge bg-warning text-dark',
            'rejected'   => 'badge bg-danger',
            default      => 'badge bg-secondary',
        };
    }

    public function getDecisionLabelAttribute(): string
    {
        return match($this->decision) {
            'selected'   => 'Selected',
            'waitlisted' => 'Waitlisted',
            'rejected'   => 'Rejected',
            default      => 'Pending',
        };
    }
}
