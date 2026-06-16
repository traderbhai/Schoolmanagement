<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{Exam, Student};
use Barryvdh\DomPDF\Facade\Pdf;

class AdmitCardController extends Controller
{
    public function download(Exam $exam)
    {
        $student = auth()->user()->student;
        if (!$student) {
            abort(403, 'No student profile linked to this account.');
        }

        abort_unless($this->isEligibleForExam($student, $exam), 403, 'You are not enrolled for this exam.');

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

        $upcomingExams = Exam::where('program_id', $student->program_id)
            ->where('exam_date', '>=', now()->toDateString())
            ->with(['subject', 'classroom', 'semester'])
            ->orderBy('exam_date')
            ->get()
            ->filter(fn($exam) => $this->isEligibleForExam($student, $exam))
            ->values();

        return view('student.admit-cards', compact('student', 'upcomingExams'));
    }

    private function isEligibleForExam(Student $student, Exam $exam): bool
    {
        if ((int) $student->program_id !== (int) $exam->program_id) {
            return false;
        }

        if (!$exam->subject_id) {
            return true;
        }

        return $student->subjectEnrollments()
            ->where('subject_id', $exam->subject_id)
            ->where('status', 'active')
            ->when($exam->term_id, fn($q) => $q->where('term_id', $exam->term_id))
            ->exists()
            || $student->enrollments()
                ->where('subject_id', $exam->subject_id)
                ->whereIn('status', ['enrolled', 'active'])
                ->when($exam->term_id, fn($q) => $q->where('term_id', $exam->term_id))
                ->when($exam->semester_id, fn($q) => $q->where('semester_id', $exam->semester_id))
                ->exists();
    }
}
