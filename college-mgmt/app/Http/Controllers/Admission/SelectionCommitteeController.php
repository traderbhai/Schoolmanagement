<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Services\AdmissionSelectionCommitteeService;
use App\Services\AdmissionSensitiveAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelectionCommitteeController extends Controller
{
    public function index()
    {
        return view('admission.v0038.selection-committee', [
            'candidates' => Applicant::with(['user', 'program', 'payments', 'documents'])->whereIn('status', ['shortlisted', 'selected', 'under_review'])->paginate(15),
            'decisions' => DB::table('admission_selection_committee_decisions')->latest()->limit(30)->get(),
            'scores' => DB::table('admission_assessment_normalized_scores')->latest()->limit(30)->get(),
        ]);
    }

    public function decide(Request $request, AdmissionSelectionCommitteeService $service, AdmissionSensitiveAuditService $audit)
    {
        $data = $request->validate([
            'applicant_id' => ['required', 'exists:applicants,id'],
            'decision' => ['required', 'in:selected,waitlist,rejected,hold,reschedule'],
            'reason' => ['required', 'string'],
            'panel_id' => ['nullable', 'integer'],
            'normalized_score' => ['nullable', 'numeric'],
        ]);

        $applicant = Applicant::findOrFail($data['applicant_id']);
        $before = $applicant->only(['status']);
        $service->decide($applicant, $data['decision'], $data['reason'], $request->user(), $data);
        $audit->record('committee_decision', $applicant->fresh(), $request->user(), $data['reason'], $before, $applicant->fresh()->only(['status']));
        return back()->with('success', 'Committee decision recorded.');
    }
}
