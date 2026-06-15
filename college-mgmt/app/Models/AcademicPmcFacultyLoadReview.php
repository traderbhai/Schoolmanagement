<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcFacultyLoadReview extends Model
{
    protected $fillable = [
        'teacher_id', 'term_id', 'generation_run_id', 'assigned_weekly_hours',
        'scheduled_classes', 'max_classes_in_day', 'max_consecutive_classes',
        'configured_weekly_limit', 'configured_daily_limit', 'load_band',
        'status', 'risk_reasons', 'daily_distribution', 'reviewed_by',
        'reviewed_at', 'decision_note', 'metadata',
    ];

    protected $casts = [
        'risk_reasons' => 'array',
        'daily_distribution' => 'array',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function generationRun() { return $this->belongsTo(AcademicPmcTimetableGenerationRun::class, 'generation_run_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
