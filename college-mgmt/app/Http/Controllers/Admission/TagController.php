<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionTag;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function tagLead(Request $request, Lead $lead)
    {
        $this->guard($request, $lead->assigned_to);
        $tag = AdmissionTag::findOrFail($request->validate(['tag_id' => ['required', 'exists:admission_tags,id']])['tag_id']);
        $lead->tags()->syncWithoutDetaching([$tag->id => ['tagged_by' => $request->user()->id]]);

        return back()->with('success', 'Lead tagged.');
    }

    public function tagApplicant(Request $request, Applicant $applicant)
    {
        $this->guard($request, $applicant->assigned_to);
        $tag = AdmissionTag::findOrFail($request->validate(['tag_id' => ['required', 'exists:admission_tags,id']])['tag_id']);
        $applicant->tags()->syncWithoutDetaching([$tag->id => ['tagged_by' => $request->user()->id]]);

        return back()->with('success', 'Applicant tagged.');
    }

    public function bulkTagLeads(Request $request)
    {
        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'tag_id' => ['required', 'exists:admission_tags,id'],
        ]);

        $count = 0;
        foreach (Lead::whereIn('id', $validated['lead_ids'])->get() as $lead) {
            $this->guard($request, $lead->assigned_to);
            $lead->tags()->syncWithoutDetaching([$validated['tag_id'] => ['tagged_by' => $request->user()->id]]);
            $count++;
        }

        return back()->with('success', "{$count} lead(s) tagged.");
    }

    public function bulkTagApplicants(Request $request)
    {
        $validated = $request->validate([
            'applicant_ids' => ['required', 'array', 'min:1'],
            'applicant_ids.*' => ['integer', 'exists:applicants,id'],
            'tag_id' => ['required', 'exists:admission_tags,id'],
        ]);

        $count = 0;
        foreach (Applicant::whereIn('id', $validated['applicant_ids'])->get() as $applicant) {
            $this->guard($request, $applicant->assigned_to);
            $applicant->tags()->syncWithoutDetaching([$validated['tag_id'] => ['tagged_by' => $request->user()->id]]);
            $count++;
        }

        return back()->with('success', "{$count} applicant(s) tagged.");
    }

    private function guard(Request $request, ?int $assignedTo): void
    {
        abort_unless($this->hierarchy->canViewAssignedUser($request->user(), 'ADM', $assignedTo, true), 403);
    }
}
