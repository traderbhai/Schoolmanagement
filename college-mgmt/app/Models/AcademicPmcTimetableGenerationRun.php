<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableGenerationRun extends Model
{
    protected $fillable = ['title', 'strategy', 'program_id', 'batch_id', 'term_id', 'timetable_version_id', 'created_by', 'status', 'scheduled_count', 'unscheduled_count', 'hard_conflict_count', 'soft_warning_count', 'quality_score', 'input_summary', 'metadata'];
    protected $casts = ['input_summary' => 'array', 'metadata' => 'array'];
    public function items() { return $this->hasMany(AcademicPmcTimetableGenerationItem::class, 'generation_run_id'); }
    public function program() { return $this->belongsTo(Program::class); }
}
