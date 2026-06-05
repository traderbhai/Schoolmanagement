<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Student, Semester, FeePayment, TimetableSlot};
use App\Services\{ReportService, TimetableService, GradeService};
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reports,
        private TimetableService $timetable
    ) {}

    public function gradeCard(Student $student, Semester $semester)
    {
        return $this->reports->gradeCardPdf($student, $semester);
    }

    public function feeReceipt(FeePayment $payment)
    {
        return $this->reports->feeReceiptPdf($payment);
    }

    public function timetable(Semester $semester)
    {
        $grid  = $this->timetable->buildWeeklyGrid($semester->id);
        $slots = TimetableSlot::orderBy('sort_order')->get();
        return $this->reports->timetablePdf($semester, $grid, $slots);
    }

    public function consolidatedReport(Student $student)
    {
        $student->load('user', 'course', 'department');
        $semesters = \App\Models\Semester::with('academicYear')
            ->orderBy('sort_order')
            ->get();

        $semesterReports = [];
        $gradeService = new GradeService();
        foreach ($semesters as $sem) {
            $report = $gradeService->calculateStudentSemesterReport($student, $sem);
            if (!empty($report['subjects'])) {
                $semesterReports[] = ['semester' => $sem, 'report' => $report];
            }
        }

        $cgpa = $gradeService->calculateCGPA($student);

        $pdf = Pdf::loadView('admin.reports.consolidated', compact('student', 'semesterReports', 'cgpa'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("report_{$student->enrollment_number}.pdf");
    }
}
