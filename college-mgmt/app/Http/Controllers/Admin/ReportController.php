<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
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
        $this->authorizeOfficialAcademicDocument();

        return $this->reports->gradeCardPdf($student, $semester);
    }

    public function feeReceipt(FeePayment $payment)
    {
        $this->authorizeOfficialFinancialDocument();

        return $this->reports->feeReceiptPdf($payment);
    }

    public function timetable(Semester $semester)
    {
        $this->authorizeOfficialAcademicDocument();

        $grid  = $this->timetable->buildWeeklyGrid($semester->id, officialOnly: true);
        $slots = TimetableSlot::orderBy('sort_order')->get();
        return $this->reports->timetablePdf($semester, $grid, $slots);
    }

    public function consolidatedReport(Student $student)
    {
        $this->authorizeOfficialAcademicDocument();

        $student->load('user', 'course', 'department');
        $semesters = \App\Models\Semester::with('academicYear')
            ->orderBy('sort_order')
            ->get();

        $semesterReports = [];
        $gradeService = new GradeService();
        foreach ($semesters as $sem) {
            $report = $gradeService->calculateStudentSemesterReport($student->id, $sem->id);
            if ($this->reports->isCompleteGradeCardReport($report['subjects'] ?? [])) {
                $semesterReports[] = ['semester' => $sem, 'report' => $report];
            }
        }

        abort_unless(! empty($semesterReports), 404, 'Consolidated report is available only after at least one complete semester result is published.');

        $cgpa = $gradeService->calculateCGPA($student->id);

        $pdf = Pdf::loadView('admin.reports.consolidated', compact('student', 'semesterReports', 'cgpa'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("report_{$student->enrollment_number}.pdf");
    }

    private function authorizeOfficialAcademicDocument(): void
    {
        abort_unless(request()->user() && AccessControl::canViewOfficialAcademicDocuments(request()->user()), 403);
    }

    private function authorizeOfficialFinancialDocument(): void
    {
        abort_unless(request()->user() && AccessControl::canViewOfficialFinancialDocuments(request()->user()), 403);
    }
}
