<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetablePublishCheck extends Model
{
    protected $fillable = [
        'generation_run_id', 'timetable_version_id', 'check_type', 'status',
        'severity', 'title', 'description', 'required_role', 'resolved_by',
        'resolved_at', 'metadata',
    ];

    protected $casts = ['resolved_at' => 'datetime', 'metadata' => 'array'];

    public function generationRun() { return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id'); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
}
