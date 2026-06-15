<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcPlanningCycle extends Model
{
    protected $fillable = ['title', 'cycle_type', 'academic_year', 'program_id', 'batch_id', 'term_id', 'owner_user_id', 'approved_by', 'status', 'readiness_score', 'starts_at', 'ends_at', 'approved_at', 'metadata'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'approved_at' => 'datetime', 'metadata' => 'array'];

    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function readinessItems() { return $this->hasMany(AcademicPmcReadinessItem::class, 'planning_cycle_id'); }
}
