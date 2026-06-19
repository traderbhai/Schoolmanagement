<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    protected function getApplicant()
    {
        return auth()->user()->applicant;
    }

    public function index()
    {
        $applicant = $this->getApplicant();
        if (!$applicant) {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'No application found.');
        }

        $installments = AdmissionFeeInstallment::where('program_id', $applicant->program_id)
            ->where(function ($q) use ($applicant) {
                $q->whereNull('batch_id')->orWhere('batch_id', $applicant->batch_id);
            })
            ->where('is_active', true)
            ->orderBy('installment_number')
            ->get();

        // Load payments keyed by installment_id
        $payments = $applicant->payments()->with('installment')->get()->keyBy('admission_fee_installment_id');

        $totalFee = $installments->sum('amount');
        $totalPaid = $applicant->total_paid;
        $outstanding = max(0, $totalFee - $totalPaid);

        return view('applicant.fees.index', compact(
            'applicant', 'installments', 'payments', 'totalFee', 'totalPaid', 'outstanding'
        ));
    }

    public function store(Request $r, AdmissionFeeInstallment $installment)
    {
        $applicant = $this->getApplicant();
        if (!$applicant) {
            return redirect()->route('applicant.dashboard')->with('error', 'No application found.');
        }

        if (!in_array($applicant->status, ['shortlisted', 'selected'])) {
            return back()->with('error', 'You can only submit payments after being shortlisted or selected.');
        }

        $installmentBelongsToApplicant = $installment->is_active
            && (int) $installment->program_id === (int) $applicant->program_id
            && (
                $installment->batch_id === null
                || (int) $installment->batch_id === (int) $applicant->batch_id
            );

        if (!$installmentBelongsToApplicant) {
            return back()->with('error', 'This fee installment is not available for your application.');
        }

        $existingActivePayment = $applicant->payments()
            ->where('admission_fee_installment_id', $installment->id)
            ->whereIn('status', ['pending', 'verified'])
            ->latest()
            ->first();

        if ($existingActivePayment) {
            $message = $existingActivePayment->status === 'verified'
                ? 'This fee installment has already been verified. Contact admissions for corrections.'
                : 'A payment proof for this fee installment is already pending verification.';

            return back()->with('error', $message);
        }

        $validated = $r->validate([
            'amount_paid'           => ['required', 'numeric', 'min:1'],
            'payment_date'          => ['required', 'date', 'before_or_equal:today'],
            'payment_mode'          => ['required', 'in:neft,rtgs,imps,upi,dd,cash,cheque'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'bank_name'             => ['nullable', 'string', 'max:100'],
            'payment_proof'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ((float) $validated['amount_paid'] > (float) $installment->amount) {
            return back()->withErrors([
                'amount_paid' => 'Payment amount cannot exceed this admission fee installment amount.',
            ])->withInput();
        }

        $transactionReference = trim((string) ($validated['transaction_reference'] ?? ''));
        $normalizedTransactionReference = strtolower($transactionReference);
        if ($transactionReference !== '' && AdmissionPayment::whereRaw('LOWER(transaction_reference) = ?', [$normalizedTransactionReference])->where('status', 'verified')->exists()) {
            return back()->withErrors([
                'transaction_reference' => 'This transaction reference is already linked to a verified admission payment.',
            ])->withInput();
        }

        if ($transactionReference !== '' && AdmissionPayment::whereRaw('LOWER(transaction_reference) = ?', [$normalizedTransactionReference])->where('status', 'pending')->exists()) {
            return back()->withErrors([
                'transaction_reference' => 'This transaction reference is already pending admission payment verification.',
            ])->withInput();
        }

        $proofPath = null;
        if ($r->hasFile('payment_proof')) {
            $proofPath = $r->file('payment_proof')->store('payment_proofs', 'local');
        }

        AdmissionPayment::create([
            'applicant_id'                  => $applicant->id,
            'admission_fee_installment_id'  => $installment->id,
            'amount_paid'                   => $validated['amount_paid'],
            'payment_date'                  => $validated['payment_date'],
            'payment_mode'                  => $validated['payment_mode'],
            'transaction_reference'         => $transactionReference !== '' ? $transactionReference : null,
            'bank_name'                     => $validated['bank_name'] ?? null,
            'payment_proof_path'            => $proofPath,
            'status'                        => 'pending',
            'submitted_by'                  => auth()->id(),
        ]);

        return redirect()->route('applicant.fees.index')
            ->with('success', 'Payment submitted successfully. It will be verified shortly.');
    }

    public function show(AdmissionPayment $payment)
    {
        $applicant = $this->getApplicant();
        if (!$applicant || $payment->applicant_id !== $applicant->id) {
            abort(403);
        }
        $payment->load('installment', 'verifiedBy');
        return view('applicant.fees.show', compact('payment', 'applicant'));
    }
}
