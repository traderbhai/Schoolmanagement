<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\Request;

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

        $applicant->update([
            'registration_fee_amount'  => $validated['amount_paid'],
            'registration_fee_paid_at' => now(),
            'registration_fee_receipt' => $validated['receipt_number'] ?? $validated['reference_number'],
        ]);

        return redirect()->route('admission.applicants.show', $applicant)
            ->with('success', 'Registration fee recorded. Application can now be submitted.');
    }
}
