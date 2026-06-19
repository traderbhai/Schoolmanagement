<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\Request;

class RegistrationFeeController extends Controller
{
    public function show()
    {
        $applicant = auth()->user()->applicant()->with(['program', 'batch'])->firstOrFail();

        return view('applicant.registration-fee', compact('applicant'));
    }

    public function store(Request $request)
    {
        $applicant = auth()->user()->applicant()->firstOrFail();

        if ($applicant->hasRegistrationFeePaid()) {
            return redirect()->route('applicant.registration-fee.show')
                ->with('error', 'Registration fee has already been recorded for your application.');
        }

        if ($applicant->status !== 'draft') {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'Registration fee details can only be submitted while the application is in draft.');
        }

        $validated = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:1', 'max:999999'],
            'payment_method' => ['required', 'in:online,bank_transfer,dd,cash'],
            'reference_number' => ['required', 'string', 'max:100'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
        ]);

        $receiptReference = trim((string) ($validated['receipt_number'] ?? $validated['reference_number']));
        if (Applicant::whereRaw('LOWER(registration_fee_receipt) = ?', [strtolower($receiptReference)])
            ->whereKeyNot($applicant->id)
            ->exists()) {
            return back()
                ->withErrors(['reference_number' => 'This registration fee reference is already linked to another application.'])
                ->withInput();
        }

        $applicant->update([
            'registration_fee_amount' => $validated['amount_paid'],
            'registration_fee_paid_at' => now(),
            'registration_fee_receipt' => $receiptReference,
        ]);

        return redirect()->route('applicant.dashboard')
            ->with('success', 'Registration fee details saved. You can now complete and submit your application.');
    }
}
