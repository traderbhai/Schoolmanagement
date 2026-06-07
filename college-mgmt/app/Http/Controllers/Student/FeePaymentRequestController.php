<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{FeePaymentRequest, FeeDemand};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeePaymentRequestController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $requests = FeePaymentRequest::where('student_id', $student->id)
            ->with('feeDemand')->orderByDesc('submitted_at')->paginate(15);
        $demands = FeeDemand::where('student_id', $student->id)
            ->where('status','!=','paid')->get();
        return view('student.fee-payment-request.index', compact('requests', 'demands'));
    }

    public function create() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $demands = FeeDemand::where('student_id', $student->id)->where('status','!=','paid')->get();
        return view('student.fee-payment-request.create', compact('demands'));
    }

    public function store(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $data = $request->validate([
            'fee_demand_id'   => 'nullable|exists:fee_demands,id',
            'amount'          => 'required|numeric|min:1',
            'payment_method'  => 'required|in:online,neft,rtgs,dd,cash',
            'bank_name'       => 'nullable|string|max:100',
            'transaction_ref' => 'nullable|string|max:100',
            'proof'           => 'nullable|file|max:5120',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store("fee-proofs/{$student->id}", 'local');
        }

        FeePaymentRequest::create([
            'student_id'      => $student->id,
            'fee_demand_id'   => $data['fee_demand_id'] ?? null,
            'amount'          => $data['amount'],
            'payment_method'  => $data['payment_method'],
            'bank_name'       => $data['bank_name'] ?? null,
            'transaction_ref' => $data['transaction_ref'] ?? null,
            'proof_path'      => $proofPath,
            'submitted_at'    => now(),
            'status'          => 'pending',
        ]);

        return redirect()->route('student.fee-payment.index')
            ->with('success', 'Payment proof submitted. Accounts will verify within 1-2 working days.');
    }
}
