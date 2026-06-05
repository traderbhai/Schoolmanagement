<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Mail\PaymentRejected;
use App\Mail\PaymentVerified;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\Program;
use App\Models\AdmissionFeeInstallment;
use App\Models\ActivityLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentVerificationController extends Controller
{
    public function pendingQueue(Request $r)
    {
        $query = AdmissionPayment::with(['applicant.program', 'applicant.user', 'installment'])
            ->where('status', 'pending')
            ->orderBy('created_at');

        if ($r->filled('program_id')) {
            $query->whereHas('applicant', fn($q) => $q->where('program_id', $r->program_id));
        }
        if ($r->filled('installment_id')) {
            $query->where('admission_fee_installment_id', $r->installment_id);
        }
        if ($r->filled('payment_mode')) {
            $query->where('payment_mode', $r->payment_mode);
        }
        if ($r->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $r->date_from);
        }
        if ($r->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $r->date_to);
        }

        $payments = $query->paginate(20)->withQueryString();

        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $installments = AdmissionFeeInstallment::orderBy('name')->get();

        $stats = [
            'total_pending'   => AdmissionPayment::where('status', 'pending')->count(),
            'verified_today'  => AdmissionPayment::where('status', 'verified')->whereDate('verified_at', today())->count(),
            'amount_pending'  => AdmissionPayment::where('status', 'pending')->sum('amount_paid'),
        ];

        return view('admission.payments.queue', compact('payments', 'programs', 'installments', 'stats'));
    }

    public function verify(Request $r, AdmissionPayment $payment)
    {
        $r->validate([
            'verification_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment->update([
            'status'             => 'verified',
            'verified_by'        => auth()->id(),
            'verified_at'        => now(),
            'verification_notes' => $r->verification_notes,
        ]);

        // If first installment verified, ensure applicant is 'selected'
        $applicant = $payment->applicant;
        if ($applicant && $applicant->status === 'shortlisted') {
            $isFirst = AdmissionFeeInstallment::where('program_id', $applicant->program_id)
                ->orderBy('installment_number')
                ->first()?->id === $payment->admission_fee_installment_id;

            if ($isFirst) {
                $applicant->update(['status' => 'selected']);
            }
        }

        ActivityLog::record('verify_payment', "Verified payment #{$payment->id} for applicant {$payment->applicant?->application_number}", $payment);

        // Send payment verified notification
        $payment->load(['applicant.user', 'installment']);
        if ($payment->applicant && $payment->applicant->user) {
            NotificationService::send(PaymentVerified::class, $payment->applicant->user, [
                'applicant' => $payment->applicant,
                'payment'   => $payment,
            ]);
        }

        return back()->with('success', 'Payment verified successfully.');
    }

    public function reject(Request $r, AdmissionPayment $payment)
    {
        $r->validate([
            'verification_notes' => ['required', 'string', 'max:1000'],
        ]);

        $payment->update([
            'status'             => 'rejected',
            'verified_by'        => auth()->id(),
            'verified_at'        => now(),
            'verification_notes' => $r->verification_notes,
        ]);

        // Send payment rejected notification
        $payment->load(['applicant.user', 'installment']);
        if ($payment->applicant && $payment->applicant->user) {
            NotificationService::send(PaymentRejected::class, $payment->applicant->user, [
                'applicant' => $payment->applicant,
                'payment'   => $payment,
            ]);
        }

        return back()->with('success', 'Payment rejected with notes.');
    }

    public function downloadProof(AdmissionPayment $payment)
    {
        if (!$payment->payment_proof_path || !Storage::disk('local')->exists($payment->payment_proof_path)) {
            abort(404, 'Proof file not found.');
        }

        return Storage::disk('local')->download($payment->payment_proof_path);
    }

    public function index(Request $r, Program $program)
    {
        $query = AdmissionPayment::with(['applicant.user', 'installment'])
            ->whereHas('applicant', fn($q) => $q->where('program_id', $program->id));

        if ($r->filled('batch_id')) {
            $query->whereHas('applicant', fn($q) => $q->where('batch_id', $r->batch_id));
        }
        if ($r->filled('installment_id')) {
            $query->where('admission_fee_installment_id', $r->installment_id);
        }
        if ($r->filled('status')) {
            $query->where('status', $r->status);
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        $installments = AdmissionFeeInstallment::where('program_id', $program->id)
            ->orderBy('installment_number')
            ->get();

        $summary = [
            'total_expected'   => $installments->sum('amount'),
            'total_verified'   => AdmissionPayment::whereHas('applicant', fn($q) => $q->where('program_id', $program->id))
                ->where('status', 'verified')->sum('amount_paid'),
            'total_pending'    => AdmissionPayment::whereHas('applicant', fn($q) => $q->where('program_id', $program->id))
                ->where('status', 'pending')->sum('amount_paid'),
            'total_rejected'   => AdmissionPayment::whereHas('applicant', fn($q) => $q->where('program_id', $program->id))
                ->where('status', 'rejected')->count(),
        ];

        $installmentBreakdown = $installments->map(function ($inst) use ($program) {
            $paymentsForInst = $inst->payments()->whereHas('applicant', fn($q) => $q->where('program_id', $program->id));
            return [
                'installment'     => $inst,
                'total_applicants'=> Applicant::where('program_id', $program->id)->whereIn('status', ['shortlisted','selected'])->count(),
                'paid_count'      => (clone $paymentsForInst)->where('status', 'verified')->count(),
                'pending_count'   => (clone $paymentsForInst)->where('status', 'pending')->count(),
                'amount_collected'=> (clone $paymentsForInst)->where('status', 'verified')->sum('amount_paid'),
            ];
        });

        return view('admission.payments.index', compact('program', 'payments', 'installments', 'summary', 'installmentBreakdown'));
    }

    public function applicantPayments(Applicant $applicant)
    {
        $applicant->load(['program', 'user', 'payments.installment', 'payments.verifiedBy']);

        $installments = AdmissionFeeInstallment::where('program_id', $applicant->program_id)
            ->where(function ($q) use ($applicant) {
                $q->whereNull('batch_id')->orWhere('batch_id', $applicant->batch_id);
            })
            ->where('is_active', true)
            ->orderBy('installment_number')
            ->get();

        $payments = $applicant->payments->keyBy('admission_fee_installment_id');

        return view('admission.payments.applicant', compact('applicant', 'installments', 'payments'));
    }
}
