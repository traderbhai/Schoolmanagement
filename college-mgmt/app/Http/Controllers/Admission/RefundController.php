<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionFeeInstallment;
use App\Models\Applicant;
use App\Models\RefundRequest;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    private const ACTIVE_REFUND_STATUSES = ['pending', 'approved', 'processed'];

    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    // List all refund requests
    public function index(Request $request)
    {
        $status = $request->get('status');
        $query = RefundRequest::with(['applicant.user', 'applicant.program', 'reviewedBy'])
            ->latest();
        $query->whereHas('applicant', function ($applicantQuery) use ($request) {
            $this->hierarchy->applyApplicantVisibility($applicantQuery, $request->user(), 'ADM');
        });
        if ($status) {
            $query->where('status', $status);
        }
        $refunds = $query->paginate(30);
        $scopedCounts = RefundRequest::whereHas('applicant', function ($applicantQuery) use ($request) {
            $this->hierarchy->applyApplicantVisibility($applicantQuery, $request->user(), 'ADM');
        });
        $counts = [
            'pending'   => (clone $scopedCounts)->where('status', 'pending')->count(),
            'approved'  => (clone $scopedCounts)->where('status', 'approved')->count(),
            'processed' => (clone $scopedCounts)->where('status', 'processed')->count(),
        ];
        return view('admission.refunds.index', compact('refunds', 'status', 'counts'));
    }

    // Show create form for a specific applicant
    public function create(Applicant $applicant)
    {
        $this->guardApplicantScope($applicant);

        $applicant->load(['user', 'program', 'batch']);
        $payments = $applicant->payments()->where('status', 'verified')->get();
        $existingRefund = RefundRequest::where('applicant_id', $applicant->id)
            ->whereIn('status', ['pending', 'approved', 'processed'])->first();
        return view('admission.refunds.create', compact('applicant', 'payments', 'existingRefund'));
    }

    // Store a new refund request
    public function store(Request $request, Applicant $applicant)
    {
        $this->guardApplicantScope($applicant);

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

        if ($this->hasActiveRefund($applicant)) {
            return back()
                ->withInput()
                ->withErrors(['refund' => 'An active refund request already exists for this applicant.']);
        }

        if ($blocker = $this->refundRequestBlocker($applicant, (float) $validated['requested_amount'], $validated['reason'], $validated['admission_payment_id'] ?? null)) {
            return back()
                ->withInput()
                ->withErrors(['requested_amount' => $blocker]);
        }

        RefundRequest::create(array_merge($validated, ['applicant_id' => $applicant->id]));

        return redirect()->route('admission.refunds.index')
            ->with('success', 'Refund request submitted for ' . ($applicant->user->name ?? 'applicant') . '.');
    }

    // Show individual refund request
    public function show(RefundRequest $refund)
    {
        $this->guardRefundScope($refund);

        $refund->load(['applicant.user', 'applicant.program', 'payment', 'reviewedBy']);
        return view('admission.refunds.show', compact('refund'));
    }

    // Approve a refund request
    public function approve(Request $request, RefundRequest $refund)
    {
        $this->guardRefundScope($refund);

        if ($refund->status !== 'pending') {
            return back()->with('error', 'Only pending refund requests can be approved.');
        }

        $request->validate([
            'approved_amount' => 'required|numeric|min:1|max:' . $refund->requested_amount,
        ]);

        if ($blocker = $this->refundRequestBlocker($refund->applicant, (float) $request->approved_amount, $refund->reason, $refund->admission_payment_id, $refund)) {
            return back()->withErrors(['approved_amount' => $blocker]);
        }

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
        $this->guardRefundScope($refund);

        if ($refund->status !== 'pending') {
            return back()->with('error', 'Only pending refund requests can be rejected.');
        }

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
        $this->guardRefundScope($refund);

        if ($refund->status !== 'approved') {
            return back()->with('error', 'Only approved refund requests can be processed.');
        }

        if ((float) ($refund->approved_amount ?? 0) <= 0) {
            return back()->with('error', 'Refund must have an approved amount before processing.');
        }

        if ($blocker = $this->refundRequestBlocker($refund->applicant, (float) $refund->approved_amount, $refund->reason, $refund->admission_payment_id, $refund)) {
            return back()->withErrors(['refund' => $blocker]);
        }

        $request->validate([
            'utr_number' => 'required|string|max:100|unique:refund_requests,utr_number,' . $refund->id,
        ]);

        $refund->update([
            'status'       => 'processed',
            'utr_number'   => $request->utr_number,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Refund marked as processed. UTR: ' . $request->utr_number);
    }

    private function hasActiveRefund(Applicant $applicant): bool
    {
        return RefundRequest::where('applicant_id', $applicant->id)
            ->whereIn('status', self::ACTIVE_REFUND_STATUSES)
            ->exists();
    }

    private function refundRequestBlocker(Applicant $applicant, float $amount, string $reason, ?int $paymentId = null, ?RefundRequest $currentRefund = null): ?string
    {
        if ($amount <= 0) {
            return 'Refund amount must be greater than zero.';
        }

        $verifiedPayments = $applicant->payments()->where('status', 'verified');
        $totalPaid = (float) (clone $verifiedPayments)->sum('amount_paid');

        if ($reason === 'withdrawal' && $applicant->status !== 'withdrawn') {
            return 'Withdrawal refunds require the applicant to be marked withdrawn first.';
        }

        if ($reason === 'rejection' && $applicant->status !== 'rejected') {
            return 'Rejection refunds require the applicant to be marked rejected first.';
        }

        if ($reason === 'excess_payment') {
            $availableExcess = max(0, $totalPaid - $this->configuredFeeTotal($applicant) - $this->existingRefundedAmount($applicant, null, $currentRefund));
            if ($amount > $availableExcess) {
                return 'Excess-payment refund amount exceeds the applicant overpaid balance.';
            }
        }

        if ($paymentId) {
            $payment = (clone $verifiedPayments)->whereKey($paymentId)->first();
            if (! $payment) {
                return 'Refunds can only be linked to a verified payment for this applicant.';
            }

            $available = (float) $payment->amount_paid - $this->existingRefundedAmount($applicant, $paymentId, $currentRefund);
            if ($amount > $available) {
                return 'Refund amount exceeds the remaining refundable balance for the linked payment.';
            }

            return null;
        }

        $available = $totalPaid - $this->existingRefundedAmount($applicant, null, $currentRefund);
        if ($amount > $available) {
            return 'Refund amount exceeds the applicant verified refundable balance.';
        }

        return null;
    }

    private function existingRefundedAmount(Applicant $applicant, ?int $paymentId = null, ?RefundRequest $currentRefund = null): float
    {
        return (float) RefundRequest::where('applicant_id', $applicant->id)
            ->whereIn('status', self::ACTIVE_REFUND_STATUSES)
            ->when($paymentId, fn ($query) => $query->where('admission_payment_id', $paymentId))
            ->when($currentRefund, fn ($query) => $query->whereKeyNot($currentRefund->id))
            ->get()
            ->sum(fn (RefundRequest $refund) => (float) ($refund->approved_amount ?? $refund->requested_amount));
    }

    private function configuredFeeTotal(Applicant $applicant): float
    {
        return (float) AdmissionFeeInstallment::where('program_id', $applicant->program_id)
            ->where(function ($query) use ($applicant) {
                $query->whereNull('batch_id')->orWhere('batch_id', $applicant->batch_id);
            })
            ->where('is_active', true)
            ->sum('amount');
    }

    private function guardApplicantScope(Applicant $applicant): void
    {
        abort_unless(
            $this->hierarchy->canViewAssignedUser(request()->user(), 'ADM', $applicant->assigned_to, false),
            403
        );
    }

    private function guardRefundScope(RefundRequest $refund): void
    {
        $refund->loadMissing('applicant');
        abort_unless($refund->applicant, 404);
        $this->guardApplicantScope($refund->applicant);
    }
}
