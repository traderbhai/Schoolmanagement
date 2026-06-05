<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExamResult extends Model
{
    use HasFactory;
    protected $fillable = [
        'exam_id', 'student_id', 'marks_obtained', 'grade', 'is_absent', 'remarks',
        'ia1_marks', 'ia2_marks', 'end_sem_marks',
    ];
    protected $casts = [
        'is_absent'      => 'boolean',
        'marks_obtained' => 'decimal:2',
        'ia1_marks'      => 'decimal:2',
        'ia2_marks'      => 'decimal:2',
        'end_sem_marks'  => 'decimal:2',
    ];

    public function exam()                    { return $this->belongsTo(Exam::class); }
    public function student()                 { return $this->belongsTo(Student::class); }
    public function assessmentComponents()    { return $this->hasMany(AssessmentComponent::class); }

    public function getPassedAttribute(): bool
    {
        return !$this->is_absent && $this->marks_obtained >= $this->exam->passing_marks;
    }

    public function getComponentScore(string $type): ?float
    {
        $component = $this->assessmentComponents()->where('component_type', $type)->first();
        return $component?->marks_obtained;
    }

    public function hasAllComponents(): bool
    {
        return $this->assessmentComponents()->count() === 3;
    }

    public function calculateCompositeScore(): float
    {
        if (!$this->hasAllComponents()) {
            return 0;
        }

        $ia1 = $this->getComponentScore('ia1') ?? 0;
        $ia2 = $this->getComponentScore('ia2') ?? 0;
        $endSem = $this->getComponentScore('end_semester') ?? 0;

        return round(($ia1 + $ia2 + $endSem), 2);
    }
}
