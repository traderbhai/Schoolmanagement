<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableResolutionAction extends Model
{
    protected $fillable = [
        'constraint_id', 'generation_run_id', 'action_type', 'title',
        'description', 'owner_user_id', 'assigned_by', 'priority', 'status',
        'due_at', 'resolution_note', 'evidence', 'metadata', 'closed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'closed_at' => 'datetime',
        'evidence' => 'array',
        'metadata' => 'array',
    ];

    public function constraint() { return $this->belongsTo(AcademicPmcTimetableConstraint::class, 'constraint_id'); }
    public function generationRun() { return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function assigner() { return $this->belongsTo(User::class, 'assigned_by'); }
}
