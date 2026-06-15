<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcStudentIntervention extends Model
{
    protected $fillable = ['student_success_plan_id', 'student_id', 'program_id', 'batch_id', 'owner_user_id', 'created_by', 'intervention_type', 'status', 'priority', 'reason', 'action_plan', 'due_at', 'completed_at', 'evidence', 'metadata'];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime', 'evidence' => 'array', 'metadata' => 'array'];

    public function plan() { return $this->belongsTo(AcademicPmcStudentSuccessPlan::class, 'student_success_plan_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function parentEscalations() { return $this->hasMany(AcademicPmcParentEscalation::class, 'intervention_id'); }
}
