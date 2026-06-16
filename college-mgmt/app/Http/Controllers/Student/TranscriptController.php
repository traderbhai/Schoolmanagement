<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\GradeService;
use Barryvdh\DomPDF\Facade\Pdf;

class TranscriptController extends Controller
{
    public function __construct(private GradeService $gradeService) {}

    public function download()
    {
        $student = auth()->user()->student;
        if (!$student) {
            abort(403, 'No student profile linked to this account.');
        }

        $semesters = $this->gradeService->semestersForStudent($student->id)
            ->load('academicYear')
            ->sortBy('number')
            ->values();

        $semesterReports = [];
        foreach ($semesters as $semester) {
            $report = $this->gradeService->calculateStudentSemesterReport($student->id, $semester->id);
            if (!empty($report['subjects'] ?? $report)) {
                $semesterReports[] = [
                    'semester' => $semester,
                    'report'   => $report,
                ];
            }
        }

        $cgpa = $this->gradeService->calculateCGPA($student->id);

        $pdf = Pdf::loadView('student.transcript-pdf', compact('student', 'semesterReports', 'cgpa'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('transcript-' . ($student->enrollment_number ?? $student->id) . '.pdf');
    }
}
