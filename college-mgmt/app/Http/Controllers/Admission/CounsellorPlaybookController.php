<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCounsellorPlaybook;
use App\Services\AdmissionAccessPolicyService;
use Illuminate\Http\Request;

class CounsellorPlaybookController extends Controller
{
    public function index()
    {
        return view('admission.v0036.counsellor-playbooks', [
            'playbooks' => AdmissionCounsellorPlaybook::with('steps')->where('is_active', true)->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        app(AdmissionAccessPolicyService::class)->authorizeApproveAdmission($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'playbook_type' => ['required', 'string', 'max:80'],
            'stage' => ['nullable', 'string', 'max:80'],
        ]);

        $playbook = AdmissionCounsellorPlaybook::create($data + ['created_by' => $request->user()->id]);
        $playbook->steps()->createMany([
            ['title' => 'Open conversation', 'body' => 'Confirm applicant objective, preferred program, and timeline.', 'suggested_action' => 'log_call', 'sort_order' => 1],
            ['title' => 'Resolve blocker', 'body' => 'Identify document, fee, assessment, or parent-decision blocker.', 'suggested_action' => 'schedule_follow_up', 'sort_order' => 2],
            ['title' => 'Commit next action', 'body' => 'Send the exact checklist or reminder and record the next follow-up.', 'suggested_action' => 'send_template', 'sort_order' => 3],
        ]);

        return back()->with('success', 'Counsellor playbook created.');
    }
}
