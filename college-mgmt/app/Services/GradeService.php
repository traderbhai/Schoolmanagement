<?php

namespace App\Services;

use App\Models\{Student, Semester, ExamResult, Enrollment};

class GradeService
{
    // Grading scale
    private array $scale = [
        [90, 'O',  10.0, 'Outstanding'],
        [80, 'A+',  9.0, 'Excellent'],
        [70, 'A',   8.0, 'Very Good'],
        [60, 'B+',  7.0, 'Good'],
        [50, 'B',   6.0, 'Average'],
        [40, 'C',   5.0, 'Pass'],
        [35, 'D',   4.0, 'Marginal Pass'],
        [0,  'F',   0.0, 'Fail'],
    ];

    public function getGrade(float $percentage): array
    {
        foreach ($this->scale as [$min, $letter, $points, $desc]) {
            if ($percentage >= $min) {
                return ['letter' => $letter, 'points' => $points, 'description' => $desc];
            }
        }
        return ['letter' => 'F', 'points' => 0.0, 'description' => 'Fail'];
    }

    public function calculateStudentSemesterReport(int $studentId, int $semesterId): array
    {
        $enrollments = Enrollment::with(['subject'])
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('status', 'active')
            ->get();

        $report = [];
        $totalCredits = 0;
        $earnedPoints = 0.0;
        $totalCreditsAttempted = 0;

        foreach ($enrollments as $enrollment) {
            $subject = $enrollment->subject;
            $results = ExamResult::whereHas('exam', fn($q) =>
                $q->where('semester_id', $semesterId)->where('subject_id', $subject->id)
            )->where('student_id', $studentId)->with('exam')->get();

            if ($results->isEmpty()) {
                $report[] = [
                    'subject'  => $subject,
                    'credits'  => $subject->credits,
                    'results'  => [],
                    'total'    => null,
                    'max'      => null,
                    'pct'      => null,
                    'grade'    => null,
                    'status'   => 'pending',
                ];
                continue;
            }

            $totalMarks    = $results->sum(fn($r) => $r->exam->total_marks);
            $obtainedMarks = $results->where('is_absent', false)->sum('marks_obtained');
            $hasAbsent     = $results->where('is_absent', true)->count() > 0;

            $pct = $totalMarks > 0 ? ($obtainedMarks / $totalMarks) * 100 : 0;
            $grade = $this->getGrade($pct);

            $totalCreditsAttempted += $subject->credits;
            if ($grade['letter'] !== 'F') {
                $totalCredits += $subject->credits;
                $earnedPoints += $subject->credits * $grade['points'];
            }

            $report[] = [
                'subject'  => $subject,
                'credits'  => $subject->credits,
                'results'  => $results,
                'total'    => $totalMarks,
                'max'      => $totalMarks,
                'obtained' => $obtainedMarks,
                'pct'      => round($pct, 2),
                'grade'    => $grade,
                'status'   => $grade['letter'] === 'F' ? 'fail' : 'pass',
                'has_absent' => $hasAbsent,
            ];
        }

        $sgpa = $totalCredits > 0 ? round($earnedPoints / $totalCreditsAttempted, 2) : 0.0;

        return [
            'subjects'             => $report,
            'total_credits'        => $totalCreditsAttempted,
            'earned_credits'       => $totalCredits,
            'earned_points'        => $earnedPoints,
            'sgpa'                 => $sgpa,
            'result'               => $totalCreditsAttempted > 0 && $totalCredits === $totalCreditsAttempted ? 'Pass' : 'Fail',
        ];
    }

    public function calculateCGPA(int $studentId): float
    {
        $semesters = \App\Models\Semester::whereHas('enrollments', function ($q) use ($studentId) {
            $q->where('student_id', $studentId);
        })->get();
        $totalPoints  = 0.0;
        $totalCredits = 0;

        foreach ($semesters as $semester) {
            $report = $this->calculateStudentSemesterReport($studentId, $semester->id);
            if ($report['total_credits'] > 0) {
                $totalPoints  += $report['earned_points'];
                $totalCredits += $report['total_credits'];
            }
        }

        return $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.0;
    }
}
