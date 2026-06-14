<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcCourseAllocationBatch extends Model
{
    protected $fillable = ['title', 'program_id', 'batch_id', 'term_id', 'owner_user_id', 'status', 'student_count', 'core_allocations', 'elective_allocations', 'conflict_count', 'rules', 'metadata'];
    protected $casts = ['rules' => 'array', 'metadata' => 'array'];
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
}
