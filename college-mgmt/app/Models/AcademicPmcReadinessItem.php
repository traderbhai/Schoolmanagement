<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcReadinessItem extends Model
{
    protected $fillable = ['planning_cycle_id', 'section', 'title', 'description', 'owner_user_id', 'status', 'severity', 'completion_percent', 'is_blocker', 'due_at', 'completed_at', 'source_type', 'source_key', 'evidence', 'metadata'];

    protected $casts = ['is_blocker' => 'boolean', 'due_at' => 'datetime', 'completed_at' => 'datetime', 'evidence' => 'array', 'metadata' => 'array'];

    public function planningCycle() { return $this->belongsTo(AcademicPmcPlanningCycle::class, 'planning_cycle_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
}
