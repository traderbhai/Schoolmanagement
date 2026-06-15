<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcCourseDeliveryCheckpoint extends Model
{
    protected $fillable = ['program_id', 'batch_id', 'term_id', 'subject_id', 'teacher_id', 'owner_user_id', 'planned_sessions', 'conducted_sessions', 'missed_sessions', 'marks_pending_count', 'attendance_percent', 'feedback_score', 'delivery_score', 'risk_band', 'status', 'next_review_at', 'signals', 'metadata'];

    protected $casts = ['attendance_percent' => 'decimal:2', 'feedback_score' => 'decimal:2', 'next_review_at' => 'datetime', 'signals' => 'array', 'metadata' => 'array'];

    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function remedialActions() { return $this->hasMany(AcademicPmcRemedialAction::class, 'checkpoint_id'); }
}
