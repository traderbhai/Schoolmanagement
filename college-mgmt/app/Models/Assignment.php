<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id', 'created_by', 'term_id', 'title', 'description',
        'instructions', 'attachment_path', 'max_marks', 'due_at',
        'allow_late_submission', 'late_penalty_percent', 'is_published',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'allow_late_submission' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function subject() { return $this->belongsTo(Subject::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function term() { return $this->belongsTo(Term::class); }
    public function submissions() { return $this->hasMany(AssignmentSubmission::class); }

    public function submissionByStudent(int $studentId): ?AssignmentSubmission
    {
        return $this->submissions()->where('student_id', $studentId)->first();
    }

    public function isOverdue(): bool
    {
        return $this->due_at->isPast();
    }
}
