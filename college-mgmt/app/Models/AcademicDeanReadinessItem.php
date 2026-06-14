<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanReadinessItem extends Model
{
    protected $fillable = ['planning_cycle_id', 'section', 'title', 'owner_user_id', 'status', 'is_blocker', 'due_at', 'source_type', 'source_key', 'metadata'];

    protected $casts = ['is_blocker' => 'boolean', 'due_at' => 'datetime', 'metadata' => 'array'];

    public function planningCycle() { return $this->belongsTo(AcademicDeanPlanningCycle::class, 'planning_cycle_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
}
