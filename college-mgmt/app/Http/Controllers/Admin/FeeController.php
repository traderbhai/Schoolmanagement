<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Jobs\SendFeeReceiptEmail;
use App\Models\{FeeStructure, FeePayment, Student, Course, AcademicYear, ActivityLog, FeeDemand, FeePaymentRequest};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FeeController extends Controller
{
    private const ACTIVE_DEMAND_STATUSES = ['pending', 'partially_paid', 'overdue'];

    public function index(Request $request) {
        $courses = Course::where('is_active', true)->orderBy('name')->get();
        $currentYear = AcademicYear::where('is_current', true)->first();

        $structuresQuery = FeeStructure::with(['course', 'academicYear'])
            ->when($request->course_id, fn($q) => $q->where('course_id', $request->course_id))
            ->orderBy('course_id')->orderBy('fee_type');

        $feeStructures = $structuresQuery->paginate(20)->withQueryString();

        $hasDemandLedger = FeeDemand::exists();
        $totalCollected = FeePayment::where('status', 'paid')->sum('amount_paid');
        if ($hasDemandLedger) {
            $totalDue = FeeDemand::sum('final_amount') + FeeDemand::whereIn('status', self::ACTIVE_DEMAND_STATUSES)->sum('penalty_amount');
            $totalPending = FeeDemand::whereIn('status', self::ACTIVE_DEMAND_STATUSES)
                ->sum(\DB::raw('final_amount + COALESCE(penalty_amount, 0)'));
            $collectionRate = $totalDue > 0 ? round((($totalDue - $totalPending) / $totalDue) * 100, 1) : 0;
            $overdueStudents = FeeDemand::where(function ($query) {
                    $query->where('status', 'overdue')
                        ->orWhere(fn($q) => $q->whereIn('status', self::ACTIVE_DEMAND_STATUSES)->whereDate('due_date', '<', now()->toDateString()));
                })
                ->distinct('student_id')
                ->count('student_id');
        } else {
            $allStructures = FeeStructure::when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))->get();
            $totalDue = $allStructures->sum('amount');
            $totalPending = max(0, $totalDue - $totalCollected);
            $collectionRate = $totalDue > 0 ? round(($totalCollected / $totalDue) * 100, 1) : 0;

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
        }

        $recentPayments = FeePayment::with(['student.user', 'feeStructure'])
            ->latest('payment_date')
            ->limit(10)
            ->get();

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
        $studentDemands = collect();
        $studentFees = collect();
        $studentPayments = collect();
        if ($request->student_id) {
            $selectedStudent = Student::with(['user','course'])->find($request->student_id);
            if ($selectedStudent) {
                $structures = FeeStructure::with(['course','academicYear'])
                    ->where('course_id', $selectedStudent->course_id)
                    ->get();
                $currentYear = AcademicYear::where('is_current', true)->first();
                $studentDemands = FeeDemand::with('term')
                    ->where('student_id', $selectedStudent->id)
                    ->orderBy('due_date')
                    ->get();
                $studentFees = FeeStructure::where('course_id', $selectedStudent->course_id)
                    ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
                    ->get();
                $studentPayments = FeePayment::where('student_id', $selectedStudent->id)->where('status','paid')->get();

                if ($studentDemands->isNotEmpty()) {
                    $balanceDue = $studentDemands
                        ->whereIn('status', self::ACTIVE_DEMAND_STATUSES)
                        ->sum(fn($demand) => (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0));
                } else {
                    $due = $studentFees->sum('amount');
                    $paid = $studentPayments->sum('amount_paid');
                    $balanceDue = max(0, $due - $paid);
                }
            }
        }

        return view('admin.fees.collect', compact(
            'students',
            'structures',
            'selectedStudent',
            'balanceDue',
            'studentDemands',
            'studentFees',
            'studentPayments'
        ));
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
        $student = Student::findOrFail($data['student_id']);
        $feeStructure = FeeStructure::findOrFail($data['fee_structure_id']);
        if ((int) $feeStructure->course_id !== (int) $student->course_id) {
            return back()
                ->withInput()
                ->withErrors(['fee_structure_id' => 'Select a fee structure for the selected student course.']);
        }

        $data['receipt_number'] = 'RCP-' . strtoupper(uniqid());
        $data['status'] = 'paid';
        $payment = FeePayment::create($data);
        $student->load('user');
        ActivityLog::record('created', "Fee payment ₹{$payment->amount_paid} for " . ($student->user->name ?? ''), $payment);
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

    public function export(Request $r)
    {
        $payments = FeePayment::with('student.user', 'student.course', 'feeStructure')
            ->when($r->course_id, fn($q) => $q->whereHas('student', fn($sq) => $sq->where('course_id', $r->course_id)))
            ->when($r->status, fn($q) => $q->where('status', $r->status))
            ->when($r->from, fn($q) => $q->whereDate('payment_date', '>=', $r->from))
            ->when($r->to, fn($q) => $q->whereDate('payment_date', '<=', $r->to))
            ->orderBy('payment_date', 'desc')
            ->get();

        $filename = 'fee_payments_' . now()->format('Ymd_His') . '.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($payments) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Receipt No','Student','Enrollment','Course','Fee Type','Amount Paid','Payment Date','Method','Status']);
            foreach ($payments as $p) {
                fputcsv($file, [
                    $p->receipt_number,
                    $p->student->user->name ?? '',
                    $p->student->enrollment_number ?? '',
                    $p->student->course->name ?? '',
                    $p->feeStructure->fee_type ?? '',
                    $p->amount_paid,
                    $p->payment_date?->format('d/m/Y') ?? '',
                    $p->payment_method,
                    $p->status,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function paymentRequests(Request $request)
    {
        $query = FeePaymentRequest::with(['student.user', 'student.course', 'feeDemand.term', 'verifier'])
            ->latest('submitted_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student.user', fn ($user) => $user->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $requests = $query->paginate(20)->withQueryString();
        $stats = [
            'pending' => FeePaymentRequest::where('status', 'pending')->count(),
            'verified_today' => FeePaymentRequest::where('status', 'verified')->whereDate('verified_at', today())->count(),
            'rejected_today' => FeePaymentRequest::where('status', 'rejected')->whereDate('verified_at', today())->count(),
        ];

        return view('admin.fees.payment-requests', compact('requests', 'stats'));
    }

    public function verifyPaymentRequest(Request $request, FeePaymentRequest $feePaymentRequest)
    {
        if ($feePaymentRequest->status !== 'pending') {
            return back()->with('error', 'Only pending payment proofs can be verified.');
        }

        $data = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $feePaymentRequest->load(['student', 'feeDemand']);
        $student = $feePaymentRequest->student;
        abort_unless($student, 404);

        $feeStructure = $this->feeStructureForStudent($student);
        if (! $feeStructure) {
            return back()->with('error', 'Cannot verify this payment proof because no fee structure is configured for the student course.');
        }

        $amount = (float) $feePaymentRequest->amount;
        $demand = $feePaymentRequest->feeDemand;
        if ($demand && in_array($demand->status, self::ACTIVE_DEMAND_STATUSES, true)) {
            $openAmount = (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0);
            if ($amount > $openAmount) {
                return back()->withErrors(['amount' => 'Payment proof amount exceeds the current demand balance.']);
            }
        }

        $payment = FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => $amount,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'RCP-' . strtoupper(uniqid()),
            'payment_method' => $this->feePaymentMethod($feePaymentRequest->payment_method),
            'transaction_id' => $feePaymentRequest->transaction_ref,
            'status' => 'paid',
            'remarks' => trim('Verified from student proof request #' . $feePaymentRequest->id . '. ' . ($data['notes'] ?? '')),
        ]);

        if ($demand && in_array($demand->status, self::ACTIVE_DEMAND_STATUSES, true)) {
            $this->applyPaymentToDemand($demand, $amount);
        }

        $feePaymentRequest->update([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'notes' => $data['notes'] ?? 'Verified by accounts.',
        ]);

        ActivityLog::record('updated', "Verified student fee proof ₹{$payment->amount_paid} for " . ($student->user->name ?? ''), $feePaymentRequest);
        SendFeeReceiptEmail::dispatch($payment);

        return back()->with('success', 'Payment proof verified and fee receipt created.');
    }

    public function rejectPaymentRequest(Request $request, FeePaymentRequest $feePaymentRequest)
    {
        if ($feePaymentRequest->status !== 'pending') {
            return back()->with('error', 'Only pending payment proofs can be rejected.');
        }

        $data = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $feePaymentRequest->update([
            'status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'notes' => $data['notes'],
        ]);

        ActivityLog::record('updated', 'Rejected student fee proof request #' . $feePaymentRequest->id, $feePaymentRequest);

        return back()->with('success', 'Payment proof rejected with staff notes.');
    }

    public function paymentRequestProof(FeePaymentRequest $feePaymentRequest)
    {
        abort_unless($feePaymentRequest->proof_path, 404);
        abort_unless(Storage::disk('local')->exists($feePaymentRequest->proof_path), 404);

        return response()->download(
            Storage::disk('local')->path($feePaymentRequest->proof_path),
            'fee-proof-' . $feePaymentRequest->id . '.' . pathinfo($feePaymentRequest->proof_path, PATHINFO_EXTENSION),
            ['Content-Type' => 'application/octet-stream']
        );
    }

    private function feeStructureForStudent(Student $student): ?FeeStructure
    {
        $currentYear = AcademicYear::where('is_current', true)->first();

        return FeeStructure::where('course_id', $student->course_id)
            ->when($currentYear, fn ($query) => $query->where('academic_year_id', $currentYear->id))
            ->orderBy('id')
            ->first()
            ?: FeeStructure::where('course_id', $student->course_id)->orderBy('id')->first();
    }

    private function feePaymentMethod(string $method): string
    {
        return in_array($method, ['cash', 'cheque', 'online', 'dd'], true) ? $method : 'online';
    }

    private function applyPaymentToDemand(FeeDemand $demand, float $amount): void
    {
        $openAmount = (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0);
        $remaining = max(0, $openAmount - $amount);

        $demand->update([
            'final_amount' => $remaining,
            'penalty_amount' => 0,
            'status' => $remaining <= 0 ? 'fully_paid' : 'partially_paid',
        ]);
    }
}
