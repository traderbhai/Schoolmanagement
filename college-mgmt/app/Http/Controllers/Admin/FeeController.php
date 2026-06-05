<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Jobs\SendFeeReceiptEmail;
use App\Models\{FeeStructure, FeePayment, Student, Course, AcademicYear};
use Illuminate\Http\Request;

class FeeController extends Controller
{
    public function index(Request $request) {
        $courses = Course::where('is_active', true)->orderBy('name')->get();
        $currentYear = AcademicYear::where('is_current', true)->first();

        $structuresQuery = FeeStructure::with(['course', 'academicYear'])
            ->when($request->course_id, fn($q) => $q->where('course_id', $request->course_id))
            ->orderBy('course_id')->orderBy('fee_type');

        $feeStructures = $structuresQuery->paginate(20)->withQueryString();

        // KPI totals (all structures for current year)
        $allStructures = FeeStructure::when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))->get();
        $totalDue = $allStructures->sum('amount');

        $totalCollected = FeePayment::where('status', 'paid')->sum('amount_paid');
        $totalPending = max(0, $totalDue - $totalCollected);
        $collectionRate = $totalDue > 0 ? round(($totalCollected / $totalDue) * 100, 1) : 0;

        $recentPayments = FeePayment::with(['student.user', 'feeStructure'])
            ->latest('payment_date')
            ->limit(10)
            ->get();

        // Overdue students: students with fee structures but balance > 0
        $overdueStudents = Student::where('status', 'active')
            ->whereHas('course.feeStructures', fn($q) => $q->when($currentYear, fn($q2) => $q2->where('academic_year_id', $currentYear->id)))
            ->get()
            ->filter(function ($student) use ($currentYear) {
                $due = FeeStructure::where('course_id', $student->course_id)
                    ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
                    ->sum('amount');
                $paid = FeePayment::where('student_id', $student->id)->where('status', 'paid')->sum('amount_paid');
                return $paid < $due;
            })->count();

        return view('admin.fees.index', compact(
            'feeStructures', 'courses', 'totalDue', 'totalCollected',
            'totalPending', 'collectionRate', 'recentPayments', 'overdueStudents'
        ));
    }

    public function create() {
        $courses = Course::where('is_active',true)->get();
        $years = AcademicYear::all();
        return view('admin.fees.create', compact('courses','years'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'course_id'        => 'required|exists:courses,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'fee_type'         => 'required|string|max:100',
            'amount'           => 'required|numeric|min:0',
            'semester_number'  => 'nullable|integer',
            'description'      => 'nullable|string',
        ]);
        FeeStructure::create($data);
        return redirect()->route('admin.fees.index')->with('success', 'Fee structure created.');
    }

    public function show(FeeStructure $fee) {
        $fee->load(['course','academicYear','payments.student.user']);
        return view('admin.fees.show', compact('fee'));
    }

    public function edit(FeeStructure $fee) {
        $courses = Course::where('is_active',true)->get();
        $years = AcademicYear::all();
        return view('admin.fees.edit', compact('fee','courses','years'));
    }

    public function update(Request $request, FeeStructure $fee) {
        $data = $request->validate([
            'course_id'        => 'required|exists:courses,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'fee_type'         => 'required|string|max:100',
            'amount'           => 'required|numeric|min:0',
            'semester_number'  => 'nullable|integer',
        ]);
        $fee->update($data);
        return redirect()->route('admin.fees.index')->with('success', 'Updated.');
    }

    public function destroy(FeeStructure $fee) {
        $fee->delete();
        return redirect()->route('admin.fees.index')->with('success', 'Deleted.');
    }

    public function collectPayment(Request $request) {
        $students = Student::with(['user', 'course'])->where('status','active')->get();
        $structures = FeeStructure::with(['course','academicYear'])->get();

        $selectedStudent = null;
        $balanceDue = 0;
        if ($request->student_id) {
            $selectedStudent = Student::with(['user','course'])->find($request->student_id);
            if ($selectedStudent) {
                $currentYear = AcademicYear::where('is_current', true)->first();
                $due = FeeStructure::where('course_id', $selectedStudent->course_id)
                    ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
                    ->sum('amount');
                $paid = FeePayment::where('student_id', $selectedStudent->id)->where('status','paid')->sum('amount_paid');
                $balanceDue = max(0, $due - $paid);
            }
        }

        return view('admin.fees.collect', compact('students', 'structures', 'selectedStudent', 'balanceDue'));
    }

    public function storePayment(Request $request) {
        $data = $request->validate([
            'student_id'       => 'required|exists:students,id',
            'fee_structure_id' => 'required|exists:fee_structures,id',
            'amount_paid'      => 'required|numeric|min:0',
            'payment_date'     => 'required|date',
            'payment_method'   => 'required|in:cash,cheque,online,neft,rtgs,upi',
            'transaction_id'   => 'nullable|string',
            'remarks'          => 'nullable|string',
        ]);
        $data['receipt_number'] = 'RCP-' . strtoupper(uniqid());
        $data['status'] = 'paid';
        $payment = FeePayment::create($data);
        SendFeeReceiptEmail::dispatch($payment);
        return redirect()->route('admin.fees.receipt', $payment)->with('success', 'Payment recorded successfully.');
    }

    public function receipt(FeePayment $payment) {
        $payment->load(['student.user', 'student.course', 'feeStructure.course', 'feeStructure.academicYear']);
        return view('admin.fees.receipt', compact('payment'));
    }

    public function report(Request $request) {
        $courses = Course::where('is_active', true)->orderBy('name')->get();
        $academicYears = AcademicYear::orderByDesc('name')->get();

        $query = FeePayment::with(['student.user', 'student.course', 'feeStructure.course'])
            ->when($request->course_id, fn($q) => $q->whereHas('student', fn($q2) => $q2->where('course_id', $request->course_id)))
            ->when($request->academic_year_id, fn($q) => $q->whereHas('feeStructure', fn($q2) => $q2->where('academic_year_id', $request->academic_year_id)))
            ->when($request->status && $request->status !== 'all', fn($q) => $q->where('status', $request->status))
            ->when($request->date_from, fn($q) => $q->whereDate('payment_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('payment_date', '<=', $request->date_to))
            ->when($request->student_name, fn($q) => $q->whereHas('student.user', fn($q2) => $q2->where('name', 'like', '%'.$request->student_name.'%')))
            ->latest('payment_date');

        $allPayments = $query->get();
        $totalAmount = $allPayments->sum('amount_paid');
        $paidCount = $allPayments->where('status', 'paid')->count();
        $pendingCount = $allPayments->where('status', 'pending')->count();

        $payments = $query->paginate(20)->withQueryString();

        return view('admin.fees.report', compact(
            'payments', 'courses', 'academicYears',
            'totalAmount', 'paidCount', 'pendingCount'
        ));
    }
}
