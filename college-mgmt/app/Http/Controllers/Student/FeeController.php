<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{FeeStructure, FeePayment, AcademicYear};
use Illuminate\Http\Request;

class FeeController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('student.dashboard');

        $currentYear = AcademicYear::where('is_current', true)->first();

        // Fee structures applicable to this student's course
        $feeStructures = FeeStructure::where('course_id', $student->course_id)
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

        return view('student.fees', compact('feeStructures', 'payments', 'totalDue', 'totalPaid', 'balance', 'currentYear'));
    }
}
