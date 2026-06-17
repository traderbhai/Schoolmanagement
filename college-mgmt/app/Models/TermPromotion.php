<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TermPromotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'current_term_id', 'promoted_to_term_id', 'cgpa', 'attendance_percentage',
        'meets_academic_criteria', 'meets_attendance_criteria', 'status', 'remarks', 'processed_by', 'processed_at',
    ];

    protected $casts = [
        'cgpa' => 'decimal:2',
        'processed_at' => 'datetime',
        'meets_academic_criteria' => 'boolean',
        'meets_attendance_criteria' => 'boolean',
    ];

    public function student()           { return $this->belongsTo(Student::class); }
    public function currentTerm()       { return $this->belongsTo(Term::class, 'current_term_id'); }
    public function promotedToTerm()    { return $this->belongsTo(Term::class, 'promoted_to_term_id'); }
    public function processedBy()       { return $this->belongsTo(User::class, 'processed_by'); }

    public function canPromote(): bool
    {
        return $this->meets_academic_criteria && $this->meets_attendance_criteria;
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, ['pending', 'on_hold'], true);
    }

    public function studentIsStillInCurrentTerm(): bool
    {
        return (int) $this->student?->current_term_id === (int) $this->current_term_id;
    }

    public function targetTermIsValidProgression(): bool
    {
        $current = $this->currentTerm;
        $target = $this->promotedToTerm;

        return $current
            && $target
            && (int) $current->program_id === (int) $target->program_id
            && (int) $current->batch_id === (int) $target->batch_id
            && (int) $target->term_number > (int) $current->term_number;
    }

    public function approve(): void
    {
        $this->update(['status' => 'approved', 'processed_at' => now(), 'processed_by' => auth()->id()]);
        $this->student->update(['current_term_id' => $this->promoted_to_term_id]);
    }

    public function reject(string $remarks = ''): void
    {
        $this->update(['status' => 'rejected', 'remarks' => $remarks, 'processed_at' => now(), 'processed_by' => auth()->id()]);
    }
}
