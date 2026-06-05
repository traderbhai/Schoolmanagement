<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Exam extends Model
{
    use HasFactory;
    protected $fillable = [
        'semester_id', 'program_id', 'term_id', 'subject_id', 'name', 'type', 'exam_date',
        'start_time', 'end_time', 'total_marks', 'passing_marks', 'classroom_id',
    ];
    protected $casts = ['exam_date' => 'date'];

    public function semester() { return $this->belongsTo(Semester::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function results() { return $this->hasMany(ExamResult::class); }
}
