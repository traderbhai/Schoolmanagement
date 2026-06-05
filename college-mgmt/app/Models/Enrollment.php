<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = ['student_id', 'semester_id', 'term_id', 'subject_id', 'status'];

    public function student() { return $this->belongsTo(Student::class); }
    public function semester() { return $this->belongsTo(Semester::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
}
