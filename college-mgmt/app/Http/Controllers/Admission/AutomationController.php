<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAutomation;
use App\Models\AdmissionAutomationExecution;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionAutomationService;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function index()
    {
        return view('admission.v003.automations', [
            'automations' => AdmissionAutomation::orderBy('priority')->get(),
            'executions' => AdmissionAutomationExecution::with('automation')->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'string', 'max:60'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'conditions_json' => ['nullable', 'json'],
            'actions_json' => ['required', 'json'],
        ]);

        AdmissionAutomation::create([
            'name' => $data['name'],
            'trigger' => $data['trigger'],
            'priority' => $data['priority'] ?? 100,
            'conditions' => json_decode($data['conditions_json'] ?: '{}', true),
            'actions' => json_decode($data['actions_json'], true),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Automation saved.');
    }

    public function run(Request $request, AdmissionAutomationService $service)
    {
        $data = $request->validate([
            'trigger' => ['required', 'string', 'max:60'],
            'subject_type' => ['required', 'in:lead,applicant'],
            'subject_id' => ['required', 'integer'],
        ]);
        $subject = $data['subject_type'] === 'lead' ? Lead::findOrFail($data['subject_id']) : Applicant::findOrFail($data['subject_id']);
        $executions = $service->run($data['trigger'], $subject, $request->user());

        return back()->with('success', $executions->count() . ' automation(s) evaluated.');
    }
}
