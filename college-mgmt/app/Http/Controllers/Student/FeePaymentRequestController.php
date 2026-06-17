<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{FeePaymentRequest, FeeDemand};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeePaymentRequestController extends Controller {
    private const ACTIVE_DEMAND_STATUSES = ['pending', 'partially_paid', 'overdue'];

    private function outstandingDemandsFor(int $studentId)
    {
        return FeeDemand::with('term')
            ->where('student_id', $studentId)
            ->whereIn('status', self::ACTIVE_DEMAND_STATUSES)
            ->orderBy('due_date')
            ->get();
    }

    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $requests = FeePaymentRequest::where('student_id', $student->id)
            ->with('feeDemand')->orderByDesc('submitted_at')->paginate(15);
        $demands = $this->outstandingDemandsFor($student->id);
        return view('student.fee-payment-request.index', compact('requests', 'demands', 'student'));
    }

    public function create() {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        if ($student->status !== 'active') {
            return redirect()->route('student.fee-payment.index')
                ->with('error', 'New fee payment proofs are available only for active students. Contact accounts for archived records.');
        }

        $demands = $this->outstandingDemandsFor($student->id);
        return view('student.fee-payment-request.create', compact('demands'));
    }

    public function store(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        if ($student->status !== 'active') {
            return redirect()->route('student.fee-payment.index')
                ->with('error', 'New fee payment proofs are available only for active students. Contact accounts for archived records.');
        }

        $data = $request->validate([
            'fee_demand_id'   => 'nullable|exists:fee_demands,id',
            'amount'          => 'required|numeric|min:1',
            'payment_method'  => 'required|in:online,neft,rtgs,dd,cash',
            'bank_name'       => 'nullable|string|max:100',
            'transaction_ref' => 'nullable|string|max:100',
            'proof'           => 'nullable|file|max:5120',
        ]);

        $outstandingDemands = $this->outstandingDemandsFor($student->id);

        if (!empty($data['fee_demand_id'])) {
            $demand = FeeDemand::where('student_id', $student->id)
                ->whereIn('status', self::ACTIVE_DEMAND_STATUSES)
                ->find($data['fee_demand_id']);

            if (!$demand) {
                return back()
                    ->withErrors(['fee_demand_id' => 'Select one of your outstanding fee demands.'])
                    ->withInput();
            }

            if (FeePaymentRequest::where('student_id', $student->id)->where('fee_demand_id', $demand->id)->where('status', 'pending')->exists()) {
                return back()
                    ->withErrors(['fee_demand_id' => 'A payment proof is already pending for this fee demand. Wait for accounts verification or contact accounts.'])
                    ->withInput();
            }

            $openAmount = $this->openAmount($demand);
            if ((float) $data['amount'] > $openAmount) {
                return back()
                    ->withErrors(['amount' => 'Payment proof amount cannot exceed the selected demand balance of INR ' . number_format($openAmount, 2) . '.'])
                    ->withInput();
            }
        } else {
            $totalOpenAmount = $outstandingDemands->sum(fn (FeeDemand $demand) => $this->openAmount($demand));
            if ((float) $data['amount'] > $totalOpenAmount) {
                return back()
                    ->withErrors(['amount' => 'Payment proof amount cannot exceed your total open fee balance of INR ' . number_format($totalOpenAmount, 2) . '.'])
                    ->withInput();
            }
        }

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

    private function openAmount(FeeDemand $demand): float
    {
        return (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0);
    }
}
