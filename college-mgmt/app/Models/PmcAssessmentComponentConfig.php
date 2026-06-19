<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmcAssessmentComponentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_subject_id',
        'program_id',
        'subject_id',
        'term_id',
        'name',
        'max_marks',
        'weightage',
        'created_by',
    ];

    protected $casts = [
        'max_marks' => 'decimal:2',
        'weightage' => 'decimal:2',
    ];

    public function programSubject() { return $this->belongsTo(ProgramSubject::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
