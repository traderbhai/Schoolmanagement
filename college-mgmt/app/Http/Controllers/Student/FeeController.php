<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{FeeStructure, FeePayment, AcademicYear, FeeDemand, HostelFeeDemand};
use Illuminate\Http\Request;

class FeeController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('student.dashboard');

        $currentYear = AcademicYear::where('is_current', true)->first();

        // Fee structures applicable to this student's course
        $feeStructures = FeeStructure::with('course')
            ->where('course_id', $student->course_id)
            ->where('academic_year_id', optional($currentYear)->id)
            ->get();

        // Payments made by this student
        $payments = FeePayment::with('feeStructure')
            ->where('student_id', $student->id)
            ->latest('payment_date')
            ->get();

        // Calculate dues
        $totalDue  = $feeStructures->sum('amount');
        $totalPaid = $payments->where('status', 'paid')->sum('amount_paid');
        $balance   = max(0, $totalDue - $totalPaid);

        // Group payments by fee_type
        $paymentsByType = $payments->groupBy(fn($p) => $p->feeStructure->fee_type ?? 'Other');

        // First unpaid fee structure amount
        $unpaidStructures = $feeStructures->filter(function ($fs) use ($payments) {
            $paid = $payments->where('fee_structure_id', $fs->id)->where('status', 'paid')->sum('amount_paid');
            return $paid < $fs->amount;
        });
        $nextDueAmount = $unpaidStructures->first()?->amount ?? 0;

        // Fee demands for this student
        $feeDemands = FeeDemand::with('term')
            ->where('student_id', $student->id)
            ->latest()
            ->get();

        $totalPendingDemands = $feeDemands->where('status', 'pending')->sum('final_amount');
        $totalPenalties = $feeDemands->sum('penalty_amount');
        $outstandingTotal = $totalPendingDemands + $totalPenalties;

        $hostelFeeDemands = HostelFeeDemand::with('allocation.room.block')
            ->where('student_id', $student->id)
            ->latest('due_date')
            ->get();

        $hostelOutstandingTotal = $hostelFeeDemands->where('status', 'pending')->sum('amount');

        return view('student.fees', compact(
            'feeStructures', 'payments', 'totalDue', 'totalPaid', 'balance',
            'currentYear', 'paymentsByType', 'nextDueAmount',
            'feeDemands', 'outstandingTotal', 'totalPenalties',
            'hostelFeeDemands', 'hostelOutstandingTotal'
        ));
    }
}
