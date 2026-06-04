<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Student, Semester, FeePayment, TimetableSlot};
use App\Services\{ReportService, TimetableService};

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
}
