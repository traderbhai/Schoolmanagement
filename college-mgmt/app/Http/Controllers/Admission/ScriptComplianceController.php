<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCallLog;
use App\Models\AdmissionScriptCompletionLog;
use App\Models\AdmissionScriptTemplate;
use App\Services\AdmissionScriptComplianceService;
use Illuminate\Http\Request;

class ScriptComplianceController extends Controller
{
    public function index()
    {
        return view('admission.v0037.script-compliance', [
            'templates' => AdmissionScriptTemplate::latest()->get(),
            'logs' => AdmissionScriptCompletionLog::with('template')->latest()->paginate(25)->withQueryString(),
        ]);
    }

    public function store(Request $request, AdmissionScriptComplianceService $service)
    {
        $data = $request->validate(['call_log_id' => ['required', 'exists:admission_call_logs,id'], 'script_template_id' => ['required', 'exists:admission_script_templates,id']]);
        $template = AdmissionScriptTemplate::findOrFail($data['script_template_id']);
        $steps = collect($template->steps)->mapWithKeys(fn ($step, $index) => [$index => $index % 3 === 0 ? 'missed' : 'covered'])->all();
        $service->log(AdmissionCallLog::findOrFail($data['call_log_id']), $template, $steps, $request->user());

        return back()->with('success', 'Script compliance logged.');
    }
}
