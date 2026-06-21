<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionPipelineService;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index(Request $request, AdmissionPipelineService $service)
    {
        $objectType = $request->get('object_type', 'lead');
        abort_unless(in_array($objectType, ['lead', 'applicant'], true), 404);

        return view('admission.v003.pipeline', [
            'objectType' => $objectType,
            'snapshot' => $service->snapshot($request->user(), $objectType, $request->only(['program_id'])),
        ]);
    }

    public function move(Request $request, AdmissionPipelineService $service)
    {
        $data = $request->validate([
            'object_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
            'stage' => ['required', 'string', 'max:60'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $subject = $data['object_type'] === 'lead' ? Lead::findOrFail($data['subject_id']) : Applicant::findOrFail($data['subject_id']);
        $service->move($subject, $data['stage'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Pipeline stage updated.');
    }
}
