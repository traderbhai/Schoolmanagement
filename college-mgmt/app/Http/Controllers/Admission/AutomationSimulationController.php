<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAutomation;
use App\Models\AdmissionAutomationConflictLog;
use App\Models\AdmissionAutomationSimulation;
use App\Services\AdmissionAutomationSchedulerService;
use App\Services\AdmissionAutomationSimulationService;
use Illuminate\Http\Request;

class AutomationSimulationController extends Controller
{
    public function index()
    {
        return view('admission.v0037.automation-simulation', [
            'automations' => AdmissionAutomation::orderBy('priority')->get(),
            'simulations' => AdmissionAutomationSimulation::latest()->limit(20)->get(),
            'conflicts' => AdmissionAutomationConflictLog::latest()->limit(20)->get(),
        ]);
    }

    public function simulate(Request $request, AdmissionAutomationSimulationService $service)
    {
        $automation = AdmissionAutomation::findOrFail($request->validate(['automation_id' => ['required', 'exists:admission_automations,id']])['automation_id']);
        $simulation = $service->simulate($automation, $request->user());

        return back()->with('success', "Simulation matched {$simulation->matched_count} record(s).");
    }

    public function run(AdmissionAutomationSchedulerService $service)
    {
        $result = $service->runDue();

        return back()->with('success', "Scheduled automation run complete: {$result['schedules']} schedule(s), {$result['executions']} execution(s).");
    }
}
