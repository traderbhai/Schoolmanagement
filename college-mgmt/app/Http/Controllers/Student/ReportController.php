<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{FeePayment, Semester};
use App\Services\ReportService;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function gradeCard(Semester $semester)
    {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        return $this->reports->gradeCardPdf($student, $semester);
    }

    public function feeReceipt(FeePayment $payment)
    {
        $student = Auth::user()->student;
        abort_unless($student && $payment->student_id === $student->id, 403);
        return $this->reports->feeReceiptPdf($payment);
    }
}
