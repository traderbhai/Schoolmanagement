<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcGroupDeliveryTracker extends Model
{
    protected $fillable = [
        'course_group_id', 'program_id', 'batch_id', 'term_id', 'subject_id',
        'teacher_id', 'owner_user_id', 'planned_sessions', 'conducted_sessions',
        'missed_sessions', 'rescheduled_sessions', 'cancelled_sessions',
        'pending_session_logs', 'attendance_percent', 'delivery_progress',
        'risk_score', 'risk_band', 'status', 'next_review_at', 'risk_reasons',
        'recommended_actions', 'metadata',
    ];

    protected $casts = [
        'attendance_percent' => 'decimal:2',
        'next_review_at' => 'datetime',
        'risk_reasons' => 'array',
        'recommended_actions' => 'array',
        'metadata' => 'array',
    ];

    public function courseGroup() { return $this->belongsTo(AcademicPmcCourseGroup::class, 'course_group_id'); }
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function sessionLogs() { return $this->hasMany(AcademicPmcSessionDeliveryLog::class, 'group_delivery_tracker_id'); }
}
