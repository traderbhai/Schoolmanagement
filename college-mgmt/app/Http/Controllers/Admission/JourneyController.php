<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionJourney;
use App\Models\Applicant;
use App\Models\Program;
use App\Services\AdmissionJourneyService;
use Illuminate\Http\Request;

class JourneyController extends Controller
{
    public function index()
    {
        return view('admission.v003.journeys', [
            'journeys' => AdmissionJourney::with('currentVersion')->latest()->get(),
            'programs' => Program::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AdmissionJourneyService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'stages_json' => ['required', 'json'],
            'documents_json' => ['nullable', 'json'],
            'enrollment_blockers_json' => ['nullable', 'json'],
            'applicant_instructions' => ['nullable', 'string'],
        ]);

        $journey = AdmissionJourney::create([
            'name' => $data['name'],
            'program_id' => $data['program_id'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $service->publish($journey, [
            'stages' => json_decode($data['stages_json'], true),
            'documents' => json_decode($data['documents_json'] ?: '[]', true),
            'enrollment_blockers' => json_decode($data['enrollment_blockers_json'] ?: '[]', true),
            'applicant_instructions' => $data['applicant_instructions'] ?? null,
        ], $request->user());

        return back()->with('success', 'Journey published.');
    }

    public function preview(Applicant $applicant, AdmissionJourneyService $service)
    {
        return response()->json($service->checklist($applicant));
    }
}
