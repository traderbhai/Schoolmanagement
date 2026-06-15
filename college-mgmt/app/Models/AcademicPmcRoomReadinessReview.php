<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcRoomReadinessReview extends Model
{
    protected $fillable = [
        'classroom_id', 'generation_run_id', 'scheduled_classes',
        'max_group_strength', 'room_capacity', 'lab_required', 'lab_ready',
        'capacity_ok', 'readiness_band', 'status', 'risk_reasons',
        'usage_distribution', 'reviewed_by', 'reviewed_at',
        'decision_note', 'metadata',
    ];

    protected $casts = [
        'lab_required' => 'boolean',
        'lab_ready' => 'boolean',
        'capacity_ok' => 'boolean',
        'risk_reasons' => 'array',
        'usage_distribution' => 'array',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function generationRun() { return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
