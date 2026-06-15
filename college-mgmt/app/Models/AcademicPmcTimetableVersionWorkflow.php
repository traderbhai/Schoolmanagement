<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableVersionWorkflow extends Model
{
    protected $fillable = [
        'timetable_version_id', 'generation_run_id', 'lifecycle_status',
        'approval_status', 'published_by', 'published_at', 'frozen_by',
        'frozen_at', 'unfrozen_by', 'unfrozen_at', 'rollback_from_version_id',
        'decision_reason', 'override_reason', 'publish_summary',
        'impact_summary', 'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'frozen_at' => 'datetime',
        'unfrozen_at' => 'datetime',
        'publish_summary' => 'array',
        'impact_summary' => 'array',
        'metadata' => 'array',
    ];

    public function timetableVersion() { return $this->belongsTo(TimetableVersion::class); }
    public function generationRun() { return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id'); }
    public function publisher() { return $this->belongsTo(User::class, 'published_by'); }
    public function freezer() { return $this->belongsTo(User::class, 'frozen_by'); }
}
