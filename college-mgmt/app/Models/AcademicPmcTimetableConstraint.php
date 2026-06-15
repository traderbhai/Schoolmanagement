<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableConstraint extends Model
{
    protected $fillable = ['generation_run_id', 'constraint_type', 'severity', 'title', 'description', 'affected_type', 'affected_key', 'recommended_fix', 'source_route', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id');
    }
}
