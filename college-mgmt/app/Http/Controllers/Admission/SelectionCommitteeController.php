<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Services\AdmissionSelectionCommitteeService;
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

    public function decide(Request $request, AdmissionSelectionCommitteeService $service)
    {
        $data = $request->validate([
            'applicant_id' => ['required', 'exists:applicants,id'],
            'decision' => ['required', 'in:selected,waitlist,rejected,hold,reschedule'],
            'reason' => ['required', 'string'],
            'panel_id' => ['nullable', 'integer'],
            'normalized_score' => ['nullable', 'numeric'],
        ]);

        $service->decide(Applicant::findOrFail($data['applicant_id']), $data['decision'], $data['reason'], $request->user(), $data);
        return back()->with('success', 'Committee decision recorded.');
    }
}
