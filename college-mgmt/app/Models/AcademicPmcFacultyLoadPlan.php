<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcFacultyLoadPlan extends Model
{
    protected $fillable = ['teacher_id', 'program_id', 'term_id', 'owner_user_id', 'planned_hours', 'allocated_hours', 'mentoring_load', 'exam_load', 'load_band', 'status', 'adjunct_required', 'constraints', 'metadata'];

    protected $casts = ['adjunct_required' => 'boolean', 'constraints' => 'array', 'metadata' => 'array'];

    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
}
