<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionLeadScore;
use App\Models\Lead;
use App\Services\AdmissionLeadScoringService;
use Illuminate\Http\Request;

class LeadScoringController extends Controller
{
    public function index()
    {
        return view('admission.v003.scoring', [
            'scores' => AdmissionLeadScore::with('lead')->latest('scored_at')->limit(100)->get(),
        ]);
    }

    public function recalculate(Request $request, AdmissionLeadScoringService $service)
    {
        $lead = Lead::findOrFail($request->validate(['lead_id' => ['required', 'exists:leads,id']])['lead_id']);
        $score = $service->score($lead, $request->user(), ['manual_priority_points' => $request->integer('manual_priority_points')]);

        return back()->with('success', "Lead scored {$score->score}/100 ({$score->band}).");
    }
}
