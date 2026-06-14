<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanOperatingRecord extends Model
{
    protected $fillable = ['record_type', 'title', 'program_id', 'batch_id', 'term_id', 'student_id', 'teacher_id', 'owner_user_id', 'status', 'severity', 'score', 'due_at', 'source_type', 'source_key', 'metrics', 'metadata'];

    protected $casts = ['due_at' => 'datetime', 'metrics' => 'array', 'metadata' => 'array'];

    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function program() { return $this->belongsTo(Program::class); }
    public function student() { return $this->belongsTo(Student::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
}
