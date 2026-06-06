<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\AdmissionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RegistrationFeeController extends Controller
{
    public function show(Applicant $applicant)
    {
        return view('admission.applicants.registration-fee', compact('applicant'));
    }

    public function store(Request $request, Applicant $applicant)
    {
        if ($applicant->hasRegistrationFeePaid()) {
            return back()->with('error', 'Registration fee has already been recorded for this applicant.');
        }

        $validated = $request->validate([
            'amount_paid'      => 'required|numeric|min:1|max:999999',
            'payment_method'   => 'required|in:online,bank_transfer,dd,cash',
            'reference_number' => 'required|string|max:100',
            'proof_document'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'receipt_number'   => 'nullable|string|max:100',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof_document')) {
            $proofPath = $request->file('proof_document')->store('payment-proofs/' . $applicant->id, 'public');
        }

        // Create an AdmissionPayment record
        AdmissionPayment::create([
            'applicant_id'     => $applicant->id,
            'amount_paid'      => $validated['amount_paid'],
            'payment_method'   => $validated['payment_method'],
            'reference_number' => $validated['reference_number'],
            'proof_document'   => $proofPath,
            'status'           => 'pending',
            'payment_type'     => 'registration_fee',
        ]);

        // Mark the applicant
        $applicant->update([
            'registration_fee_amount'  => $validated['amount_paid'],
            'registration_fee_paid_at' => now(),
            'registration_fee_receipt' => $validated['receipt_number'] ?? $validated['reference_number'],
        ]);

        return redirect()->route('admission.applicants.show', $applicant)
            ->with('success', 'Registration fee recorded. Application can now be submitted.');
    }
}
