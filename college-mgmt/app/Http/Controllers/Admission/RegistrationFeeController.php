<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class RegistrationFeeController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function show(Applicant $applicant)
    {
        $this->guardApplicantScope($applicant);

        $registrationFeeLocked = $this->isFinalApplicantState($applicant);

        return view('admission.applicants.registration-fee', compact('applicant', 'registrationFeeLocked'));
    }

    public function store(Request $request, Applicant $applicant)
    {
        $this->guardApplicantScope($applicant);

        if ($applicant->hasRegistrationFeePaid()) {
            return back()->with('error', 'Registration fee has already been recorded for this applicant.');
        }

        if ($this->isFinalApplicantState($applicant)) {
            return back()->with('error', 'Registration fee cannot be recorded because this application is already in a final admission state.');
        }

        $validated = $request->validate([
            'amount_paid'      => 'required|numeric|min:1|max:999999',
            'payment_method'   => 'required|in:online,bank_transfer,dd,cash',
            'reference_number' => 'required|string|max:100',
            'proof_document'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'receipt_number'   => 'nullable|string|max:100',
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
            'registration_fee_amount'  => $validated['amount_paid'],
            'registration_fee_paid_at' => now(),
            'registration_fee_receipt' => $receiptReference,
        ]);

        return redirect()->route('admission.applicants.show', $applicant)
            ->with('success', 'Registration fee recorded. Application can now be submitted.');
    }

    private function isFinalApplicantState(Applicant $applicant): bool
    {
        return in_array($applicant->status, ['rejected', 'withdrawn', 'enrolled'], true);
    }

    private function guardApplicantScope(Applicant $applicant): void
    {
        abort_unless(
            $this->hierarchy->canViewAssignedUser(auth()->user(), 'ADM', $applicant->assigned_to, false),
            403
        );
    }
}
