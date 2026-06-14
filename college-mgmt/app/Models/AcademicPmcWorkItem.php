<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcWorkItem extends Model
{
    protected $fillable = ['work_type', 'title', 'description', 'program_id', 'batch_id', 'term_id', 'subject_id', 'student_id', 'teacher_id', 'owner_user_id', 'assigned_by', 'priority', 'status', 'severity', 'due_at', 'source_type', 'source_key', 'metrics', 'metadata'];

    protected $casts = ['due_at' => 'datetime', 'metrics' => 'array', 'metadata' => 'array'];

    public function program() { return $this->belongsTo(Program::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function student() { return $this->belongsTo(Student::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
}
