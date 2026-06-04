<?php
namespace App\Services;

use App\Models\{Student, Exam, FeePayment, FeeStructure, Semester};
use Barryvdh\DomPDF\Facade\Pdf;

class ReportService
{
    public function __construct(private GradeService $gradeService) {}

    public function gradeCardPdf(Student $student, Semester $semester)
    {
        $report  = $this->gradeService->calculateStudentSemesterReport($student->id, $semester->id);
        $results = $report['subjects'];
        $sgpa    = $report['sgpa'];
        $cgpa    = $this->gradeService->calculateCGPA($student->id);

        $pdf = Pdf::loadView('pdf.grade-card', compact('student', 'semester', 'results', 'sgpa', 'cgpa'));
        return $pdf->setPaper('a4')->stream("grade-card-{$student->enrollment_number}-sem{$semester->id}.pdf");
    }

    public function feeReceiptPdf(FeePayment $payment)
    {
        $payment->load(['student.user', 'student.course', 'feeStructure']);
        $pdf = Pdf::loadView('pdf.fee-receipt', compact('payment'));
        return $pdf->setPaper('a4')->stream("fee-receipt-{$payment->receipt_number}.pdf");
    }

    public function timetablePdf(Semester $semester, array $grid, $slots)
    {
        $pdf = Pdf::loadView('pdf.timetable', compact('semester', 'grid', 'slots'));
        return $pdf->setPaper('a4', 'landscape')->stream("timetable-{$semester->name}.pdf");
    }
}
