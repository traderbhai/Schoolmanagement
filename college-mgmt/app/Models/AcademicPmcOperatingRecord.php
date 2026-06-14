<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcOperatingRecord extends Model
{
    protected $fillable = ['record_type', 'category', 'title', 'description', 'program_id', 'batch_id', 'term_id', 'subject_id', 'student_id', 'teacher_id', 'owner_user_id', 'created_by', 'status', 'priority', 'risk_band', 'score', 'due_at', 'completed_at', 'source_type', 'source_key', 'source_route', 'metrics', 'checklist', 'payload'];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime', 'metrics' => 'array', 'checklist' => 'array', 'payload' => 'array'];

    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function student() { return $this->belongsTo(Student::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
