<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    protected $fillable = ['exam_id', 'student_id', 'marks_obtained', 'grade', 'is_absent', 'remarks'];
    protected $casts = ['is_absent' => 'boolean', 'marks_obtained' => 'decimal:2'];

    public function exam() { return $this->belongsTo(Exam::class); }
    public function student() { return $this->belongsTo(Student::class); }

    public function getPassedAttribute(): bool
    {
        return !$this->is_absent && $this->marks_obtained >= $this->exam->passing_marks;
    }
}
