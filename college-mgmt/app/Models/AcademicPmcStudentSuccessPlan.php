<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcStudentSuccessPlan extends Model
{
    protected $fillable = ['student_id', 'program_id', 'batch_id', 'mentor_user_id', 'risk_type', 'risk_band', 'status', 'intervention_plan', 'next_review_at', 'parent_escalation_required', 'signals', 'metadata'];

    protected $casts = ['next_review_at' => 'datetime', 'parent_escalation_required' => 'boolean', 'signals' => 'array', 'metadata' => 'array'];

    public function student() { return $this->belongsTo(Student::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function mentor() { return $this->belongsTo(User::class, 'mentor_user_id'); }
}
