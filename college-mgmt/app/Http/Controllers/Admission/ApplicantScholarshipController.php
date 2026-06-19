<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantScholarship;
use App\Models\Notification;
use App\Models\ScholarshipScheme;
use App\Models\User;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class ApplicantScholarshipController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    // Award a scholarship to an applicant
    public function store(Request $request, Applicant $applicant)
    {
        $this->guardApplicantScope($applicant);

        $validated = $request->validate([
            'scheme_id'      => 'required|exists:scholarship_schemes,id',
            'awarded_amount' => 'required|numeric|min:1',
            'notes'          => 'nullable|string|max:500',
        ]);

        $scheme = ScholarshipScheme::findOrFail($validated['scheme_id']);

        $eligibilityError = $this->awardEligibilityError($applicant, $scheme);
        if ($eligibilityError) {
            return back()->withErrors(['scheme_id' => $eligibilityError]);
        }

        // Validate amount does not exceed scheme max
        if ($validated['awarded_amount'] > $scheme->max_amount) {
            return back()->withErrors(['awarded_amount' => 'Amount exceeds scheme maximum of ₹' . number_format($scheme->max_amount, 2)]);
        }

        // Check seats remaining
        if ($scheme->available_seats !== null && $scheme->seatsRemaining() <= 0) {
            return back()->withErrors(['scheme_id' => 'No seats remaining for this scholarship scheme.']);
        }

        // Prevent duplicate award for same scheme
        $exists = ApplicantScholarship::where('applicant_id', $applicant->id)
            ->where('scheme_id', $scheme->id)
            ->whereIn('status', ['awarded', 'disbursed'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['scheme_id' => 'This applicant has already been awarded this scholarship.']);
        }

        ApplicantScholarship::create([
            'applicant_id'   => $applicant->id,
            'scheme_id'      => $scheme->id,
            'awarded_amount' => $validated['awarded_amount'],
            'notes'          => $validated['notes'] ?? null,
            'status'         => 'awarded',
            'awarded_by'     => auth()->id(),
            'awarded_at'     => now(),
        ]);

        // Notify the applicant
        Notification::create([
            'user_id' => $applicant->user_id,
            'title'   => 'Scholarship Awarded',
            'message' => 'You have been awarded the ' . $scheme->name . ' scholarship of ₹' . number_format($validated['awarded_amount'], 2) . '.',
            'type'    => 'info',
        ]);

        return back()->with('success', 'Scholarship awarded: ' . $scheme->name . ' (₹' . number_format($validated['awarded_amount'], 2) . ')');
    }

    // Cancel/revoke a scholarship
    public function destroy(ApplicantScholarship $scholarship)
    {
        $this->guardScholarshipScope($scholarship);

        if ($scholarship->status === 'disbursed') {
            return back()->withErrors(['scholarship' => 'Cannot cancel a disbursed scholarship.']);
        }

        $scholarship->update(['status' => 'cancelled']);
        return back()->with('success', 'Scholarship cancelled.');
    }

    // Mark scholarship as disbursed (accounts officer)
    public function disburse(Request $request, ApplicantScholarship $scholarship)
    {
        $this->guardScholarshipScope($scholarship);

        $validated = $request->validate([
            'disbursement_ref' => 'required|string|max:100',
            'notes'            => 'nullable|string|max:500',
        ]);
        $validated['disbursement_ref'] = trim($validated['disbursement_ref']);

        if ($validated['disbursement_ref'] === '') {
            return back()->withErrors(['disbursement_ref' => 'Disbursement reference is required.'])->withInput();
        }

        if ($this->disbursementReferenceExists($validated['disbursement_ref'], $scholarship)) {
            return back()->withErrors(['disbursement_ref' => 'This disbursement reference is already linked to another applicant scholarship.'])->withInput();
        }

        if ($scholarship->status !== 'awarded') {
            return back()->withErrors(['scholarship' => 'Only awarded scholarships can be disbursed.']);
        }

        if ($blocker = $this->disbursementBlocker($scholarship)) {
            return back()->withErrors(['scholarship' => $blocker]);
        }

        $notes = $scholarship->notes;
        if (! empty($validated['notes'])) {
            $notes = trim(($notes ? $notes . "\n" : '') . 'Disbursement note: ' . $validated['notes']);
        }

        $scholarship->update([
            'status'           => 'disbursed',
            'disbursement_ref' => $validated['disbursement_ref'],
            'disbursed_at'     => now(),
            'notes'            => $notes,
        ]);
        $request->merge(['disbursement_ref' => $validated['disbursement_ref']]);

        // Notify applicant
        Notification::create([
            'user_id' => $scholarship->applicant->user_id,
            'title'   => 'Scholarship Disbursed',
            'message' => 'Your scholarship amount of ₹' . number_format($scholarship->awarded_amount, 2) . ' has been disbursed. Ref: ' . $request->disbursement_ref,
            'type'    => 'success',
        ]);

        return back()->with('success', 'Scholarship marked as disbursed. Ref: ' . $request->disbursement_ref);
    }

    // Accounts officer disbursement queue
    public function disbursementQueue(Request $request)
    {
        $query = ApplicantScholarship::with(['applicant.user', 'applicant.program', 'scheme'])
            ->where('status', 'awarded')
            ->latest('awarded_at');
        $query->whereHas('applicant', function ($applicantQuery) use ($request) {
            $this->hierarchy->applyApplicantVisibility($applicantQuery, $request->user(), 'ADM');
        });

        if ($request->filled('program_id')) {
            $query->whereHas('applicant', fn($q) => $q->where('program_id', $request->program_id));
        }

        $pending   = $query->paginate(20)->withQueryString();
        $programs  = \App\Models\Program::where('is_active', true)->orderBy('name')->get();
        $scopedTotals = ApplicantScholarship::whereHas('applicant', function ($applicantQuery) use ($request) {
            $this->hierarchy->applyApplicantVisibility($applicantQuery, $request->user(), 'ADM');
        });
        $totalPendingAmount = (clone $scopedTotals)->where('status', 'awarded')->sum('awarded_amount');
        $totalDisbursed     = (clone $scopedTotals)->where('status', 'disbursed')->sum('awarded_amount');

        return view('admission.scholarship-disbursements.index', compact('pending', 'programs', 'totalPendingAmount', 'totalDisbursed'));
    }

    private function awardEligibilityError(Applicant $applicant, ScholarshipScheme $scheme): ?string
    {
        if (in_array($applicant->status, ['rejected', 'withdrawn', 'enrolled'], true)) {
            return 'Scholarships cannot be awarded after an applicant is rejected or withdrawn.';
        }

        if (! $scheme->is_active) {
            return 'This scholarship scheme is inactive.';
        }

        if ($scheme->program_id && (int) $scheme->program_id !== (int) $applicant->program_id) {
            return 'This scholarship scheme is not available for the applicant program.';
        }

        $cgpa = $this->applicantCgpa($applicant);
        if ($scheme->min_cgpa !== null && ($cgpa === null || $cgpa < (float) $scheme->min_cgpa)) {
            return 'Applicant does not meet the minimum CGPA requirement of '.number_format((float) $scheme->min_cgpa, 2).'.';
        }

        $familyIncome = $this->applicantFamilyIncome($applicant);
        if ($scheme->max_family_income !== null && $familyIncome === null) {
            return 'Applicant family income is required for this scholarship scheme.';
        }

        if ($scheme->max_family_income !== null && $familyIncome > (float) $scheme->max_family_income) {
            return 'Applicant family income exceeds the scholarship limit of Rs. '.number_format((float) $scheme->max_family_income, 0).'.';
        }

        if ($scheme->requires_document && ! $applicant->documents()->where('status', 'verified')->exists()) {
            return 'A verified applicant document is required before awarding this scholarship.';
        }

        return null;
    }

    private function disbursementBlocker(ApplicantScholarship $scholarship): ?string
    {
        $scholarship->loadMissing(['applicant', 'scheme']);
        $applicant = $scholarship->applicant;
        $scheme = $scholarship->scheme;

        if (! $applicant || ! $scheme) {
            return 'Scholarship award is missing a valid applicant or scheme.';
        }

        if (in_array($applicant->status, ['rejected', 'withdrawn', 'enrolled'], true)) {
            return 'Scholarships cannot be disbursed after an applicant is rejected or withdrawn.';
        }

        if ($eligibilityError = $this->awardEligibilityError($applicant, $scheme)) {
            return $eligibilityError;
        }

        if ((float) $scholarship->awarded_amount <= 0) {
            return 'Scholarship award amount must be greater than zero before disbursement.';
        }

        if ((float) $scheme->max_amount > 0 && (float) $scholarship->awarded_amount > (float) $scheme->max_amount) {
            return 'Scholarship award amount exceeds the current scheme maximum.';
        }

        return null;
    }

    private function applicantCgpa(Applicant $applicant): ?float
    {
        $data = $applicant->academic_data ?? [];
        foreach (['cgpa', 'last_cgpa', 'graduation_cgpa', 'percentage', 'last_percentage'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $value = (float) $data[$key];
                return $value > 10 ? round($value / 10, 2) : $value;
            }
        }

        return $applicant->scores()->exists()
            ? round((float) $applicant->scores()->avg('score'), 2)
            : null;
    }

    private function applicantFamilyIncome(Applicant $applicant): ?float
    {
        $data = $applicant->family_data ?? [];
        foreach (['annual_income', 'family_income', 'parent_income', 'guardian_income'] as $key) {
            if (! empty($data[$key])) {
                $numeric = preg_replace('/[^0-9.]/', '', (string) $data[$key]);
                return $numeric === '' ? null : (float) $numeric;
            }
        }

        return null;
    }

    private function guardApplicantScope(Applicant $applicant): void
    {
        abort_unless(
            $this->hierarchy->canViewAssignedUser(request()->user(), 'ADM', $applicant->assigned_to, false),
            403
        );
    }

    private function guardScholarshipScope(ApplicantScholarship $scholarship): void
    {
        $scholarship->loadMissing('applicant');
        abort_unless($scholarship->applicant, 404);
        $this->guardApplicantScope($scholarship->applicant);
    }

    private function disbursementReferenceExists(string $reference, ApplicantScholarship $scholarship): bool
    {
        $normalized = strtolower(trim($reference));

        return ApplicantScholarship::query()
            ->whereNotNull('disbursement_ref')
            ->whereRaw('LOWER(disbursement_ref) = ?', [$normalized])
            ->whereKeyNot($scholarship->id)
            ->exists();
    }
}
