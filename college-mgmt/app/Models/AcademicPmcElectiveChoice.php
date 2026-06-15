<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcElectiveChoice extends Model
{
    protected $fillable = [
        'student_id', 'program_id', 'batch_id', 'term_id', 'subject_id',
        'preference_rank', 'priority_score', 'status', 'choice_source',
        'decision_reason', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function student() { return $this->belongsTo(Student::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
}
