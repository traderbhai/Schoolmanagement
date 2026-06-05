<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssessmentComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_result_id', 'component_type', 'marks_obtained', 'max_marks',
        'remarks', 'marked_by', 'marked_at',
    ];

    protected $casts = [
        'marks_obtained' => 'decimal:2',
        'max_marks'      => 'decimal:2',
        'marked_at'      => 'datetime',
    ];

    public function examResult()  { return $this->belongsTo(ExamResult::class); }
    public function markedBy()    { return $this->belongsTo(User::class, 'marked_by'); }

    public function getPercentageAttribute(): float
    {
        return $this->max_marks > 0 ? round(($this->marks_obtained / $this->max_marks) * 100, 2) : 0;
    }

    public function getComponentNameAttribute(): string
    {
        return match($this->component_type) {
            'ia1'          => 'Internal Assessment 1 (IA1)',
            'ia2'          => 'Internal Assessment 2 (IA2)',
            'end_semester' => 'End Semester Exam',
            default        => $this->component_type,
        };
    }

    public function getComponentShortAttribute(): string
    {
        return match($this->component_type) {
            'ia1'          => 'IA1',
            'ia2'          => 'IA2',
            'end_semester' => 'End-Sem',
            default        => $this->component_type,
        };
    }
}
