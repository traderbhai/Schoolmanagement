<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id', 'created_by', 'term_id', 'title', 'description',
        'duration_minutes', 'total_marks', 'pass_marks', 'starts_at', 'ends_at',
        'shuffle_questions', 'show_result_immediately', 'is_published',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'shuffle_questions' => 'boolean',
        'show_result_immediately' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function subject() { return $this->belongsTo(Subject::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function term() { return $this->belongsTo(Term::class); }
    public function questions() { return $this->hasMany(QuizQuestion::class)->orderBy('order'); }
    public function attempts() { return $this->hasMany(QuizAttempt::class); }

    public function attemptByStudent(int $studentId): ?QuizAttempt
    {
        return $this->attempts()->where('student_id', $studentId)->latest()->first();
    }

    public function isActive(): bool
    {
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) return false;
        if ($this->ends_at && $now->gt($this->ends_at)) return false;
        return $this->is_published;
    }
}
