<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableSolverAttempt extends Model
{
    protected $fillable = [
        'generation_run_id', 'strategy', 'status', 'placements_attempted',
        'placements_scheduled', 'placements_unscheduled', 'hard_conflicts',
        'soft_warnings', 'quality_score', 'diagnostics',
    ];

    protected $casts = ['diagnostics' => 'array'];

    public function generationRun() { return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id'); }
}
