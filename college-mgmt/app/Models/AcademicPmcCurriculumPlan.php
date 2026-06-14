<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcCurriculumPlan extends Model
{
    protected $fillable = ['title', 'program_id', 'batch_id', 'term_id', 'owner_user_id', 'status', 'approval_status', 'readiness_score', 'credit_rules', 'obe_requirements', 'compliance_rules', 'rollout_due_at', 'metadata'];

    protected $casts = ['credit_rules' => 'array', 'obe_requirements' => 'array', 'compliance_rules' => 'array', 'rollout_due_at' => 'datetime', 'metadata' => 'array'];

    public function program() { return $this->belongsTo(Program::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
}
