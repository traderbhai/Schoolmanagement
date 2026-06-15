<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcParentEscalation extends Model
{
    protected $fillable = ['student_success_plan_id', 'intervention_id', 'student_id', 'owner_user_id', 'created_by', 'guardian_name', 'guardian_phone', 'reason', 'status', 'scheduled_at', 'completed_at', 'outcome_note', 'metadata'];

    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];

    public function plan() { return $this->belongsTo(AcademicPmcStudentSuccessPlan::class, 'student_success_plan_id'); }
    public function intervention() { return $this->belongsTo(AcademicPmcStudentIntervention::class, 'intervention_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
