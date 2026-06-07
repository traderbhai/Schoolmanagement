<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Enrollment;
use Barryvdh\DomPDF\Facade\Pdf;

class AdmitCardController extends Controller
{
    public function download(Exam $exam)
    {
        $student = auth()->user()->student;
        if (!$student) {
            abort(403, 'No student profile linked to this account.');
        }

        // Verify the student is enrolled in this exam's program/semester
        $enrolled = Enrollment::where('student_id', $student->id)
            ->when($exam->semester_id, fn($q) => $q->where('semester_id', $exam->semester_id))
            ->exists();

        // Fallback: check same program if no semester on exam
        if (!$enrolled) {
            $enrolled = $student->program_id === $exam->program_id;
        }

        if (!$enrolled) {
            abort(403, 'You are not enrolled for this exam.');
        }

        $exam->load(['program', 'subject', 'classroom', 'semester.academicYear', 'term']);

        $pdf = Pdf::loadView('student.admit-card-pdf', compact('student', 'exam'))
            ->setPaper('a5', 'portrait');

        $filename = 'admit-card-' . ($student->enrollment_number ?? $student->id)
                  . '-' . $exam->id . '.pdf';

        return $pdf->download($filename);
    }

    public function index()
    {
        $student = auth()->user()->student;
        if (!$student) {
            abort(403, 'No student profile linked to this account.');
        }

        // Upcoming exams for the student's program
        $upcomingExams = Exam::where('program_id', $student->program_id)
            ->where('exam_date', '>=', now()->toDateString())
            ->with(['subject', 'classroom', 'semester'])
            ->orderBy('exam_date')
            ->get();

        return view('student.admit-cards', compact('student', 'upcomingExams'));
    }
}
