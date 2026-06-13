<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\AdmissionDuplicateMergeService;
use Illuminate\Http\Request;

class LeadMergeController extends Controller
{
    public function __construct(private AdmissionDuplicateMergeService $mergeService) {}

    public function merge(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'duplicate_lead_id' => ['required', 'integer', 'exists:leads,id'],
        ]);

        $this->mergeService->merge($lead, Lead::findOrFail($validated['duplicate_lead_id']), $request->user());

        return back()->with('success', 'Duplicate lead merged.');
    }
}
