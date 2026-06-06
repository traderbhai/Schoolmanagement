<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\RefundRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    // List all refund requests
    public function index(Request $request)
    {
        $status = $request->get('status');
        $query = RefundRequest::with(['applicant.user', 'applicant.program', 'reviewedBy'])
            ->latest();
        if ($status) {
            $query->where('status', $status);
        }
        $refunds = $query->paginate(30);
        $counts = [
            'pending'   => RefundRequest::where('status', 'pending')->count(),
            'approved'  => RefundRequest::where('status', 'approved')->count(),
            'processed' => RefundRequest::where('status', 'processed')->count(),
        ];
        return view('admission.refunds.index', compact('refunds', 'status', 'counts'));
    }

    // Show create form for a specific applicant
    public function create(Applicant $applicant)
    {
        $applicant->load(['user', 'program', 'batch']);
        $payments = $applicant->payments()->where('status', 'verified')->get();
        $existingRefund = RefundRequest::where('applicant_id', $applicant->id)
            ->whereIn('status', ['pending', 'approved', 'processed'])->first();
        return view('admission.refunds.create', compact('applicant', 'payments', 'existingRefund'));
    }

    // Store a new refund request
    public function store(Request $request, Applicant $applicant)
    {
        $validated = $request->validate([
            'admission_payment_id' => 'nullable|exists:admission_payments,id',
            'requested_amount'     => 'required|numeric|min:1',
            'reason'               => 'required|in:withdrawal,rejection,excess_payment,other',
            'reason_detail'        => 'nullable|string|max:500',
            'bank_name'            => 'required|string|max:255',
            'account_number'       => 'required|string|max:100',
            'ifsc_code'            => 'required|string|size:11',
            'account_holder_name'  => 'required|string|max:255',
        ]);

        RefundRequest::create(array_merge($validated, ['applicant_id' => $applicant->id]));

        return redirect()->route('admission.refunds.index')
            ->with('success', 'Refund request submitted for ' . ($applicant->user->name ?? 'applicant') . '.');
    }

    // Show individual refund request
    public function show(RefundRequest $refund)
    {
        $refund->load(['applicant.user', 'applicant.program', 'payment', 'reviewedBy']);
        return view('admission.refunds.show', compact('refund'));
    }

    // Approve a refund request
    public function approve(Request $request, RefundRequest $refund)
    {
        $request->validate([
            'approved_amount' => 'required|numeric|min:0|max:' . $refund->requested_amount,
        ]);

        $refund->update([
            'status'          => 'approved',
            'approved_amount' => $request->approved_amount,
            'reviewed_by'     => auth()->id(),
            'reviewed_at'     => now(),
        ]);

        return back()->with('success', 'Refund approved for ₹' . number_format($request->approved_amount, 2));
    }

    // Reject a refund request
    public function reject(Request $request, RefundRequest $refund)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $refund->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by'      => auth()->id(),
            'reviewed_at'      => now(),
        ]);

        return back()->with('success', 'Refund request rejected.');
    }

    // Mark refund as processed (payment sent)
    public function process(Request $request, RefundRequest $refund)
    {
        $request->validate([
            'utr_number' => 'required|string|max:100',
        ]);

        $refund->update([
            'status'       => 'processed',
            'utr_number'   => $request->utr_number,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Refund marked as processed. UTR: ' . $request->utr_number);
    }
}
