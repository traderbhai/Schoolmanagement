<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentSubjectEnrollment extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'subject_id', 'term_id', 'enrollment_type', 'status'];

    public function student() { return $this->belongsTo(Student::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function term() { return $this->belongsTo(Term::class); }
}
