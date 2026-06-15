<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcRemedialAction extends Model
{
    protected $fillable = ['checkpoint_id', 'subject_id', 'teacher_id', 'owner_user_id', 'created_by', 'action_type', 'status', 'priority', 'reason', 'action_plan', 'due_at', 'completed_at', 'evidence', 'metadata'];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime', 'evidence' => 'array', 'metadata' => 'array'];

    public function checkpoint() { return $this->belongsTo(AcademicPmcCourseDeliveryCheckpoint::class, 'checkpoint_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
