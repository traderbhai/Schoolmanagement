<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicTranscript;
use Barryvdh\DomPDF\Facade\Pdf;

class TranscriptController extends Controller
{
    public function download()
    {
        $student = auth()->user()->student;
        if (!$student) {
            abort(403, 'No student profile linked to this account.');
        }

        $student->load(['user', 'program', 'batch']);

        $transcript = AcademicTranscript::where('student_id', $student->id)
            ->where('status', 'issued')
            ->latest('issued_at')
            ->latest('id')
            ->first();

        if (! $transcript || ! $this->hasUsableSnapshot($transcript)) {
            return redirect()->route('student.results')
                ->with('error', 'Your official transcript has not been issued yet. Contact the Exam Cell for transcript issuance.');
        }

        $snapshot = $transcript->semester_data;
        $semesterReports = $snapshot['semester_reports'];
        $cgpa = (float) ($snapshot['cgpa'] ?? $transcript->cgpa);
        $totalCredits = (int) ($snapshot['total_credits'] ?? $transcript->total_credits_earned);
        $issuedAt = $transcript->issued_at ?? $transcript->created_at ?? now();

        $pdf = Pdf::loadView('pdf.academic-transcript', compact('student', 'semesterReports', 'cgpa', 'totalCredits', 'issuedAt'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('transcript-' . ($student->enrollment_number ?? $student->id) . '.pdf');
    }

    private function hasUsableSnapshot(AcademicTranscript $transcript): bool
    {
        return is_array($transcript->semester_data)
            && array_key_exists('semester_reports', $transcript->semester_data)
            && array_key_exists('cgpa', $transcript->semester_data)
            && array_key_exists('total_credits', $transcript->semester_data);
    }
}
