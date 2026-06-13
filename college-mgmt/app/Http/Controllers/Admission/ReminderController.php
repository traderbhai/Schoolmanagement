<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCadenceRule;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\AdmissionReminderSchedule;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionCadenceService;
use App\Services\AdmissionReminderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReminderController extends Controller
{
    public function index(Request $request, AdmissionReminderService $reminders)
    {
        return view('admission.v0031.reminders', [
            'reminders' => $reminders->dueFor($request->user(), $request->only(['status', 'reason', 'date'])),
            'templates' => AdmissionCommunicationTemplate::where('is_active', true)->orderBy('name')->get(),
            'cadenceRules' => AdmissionCadenceRule::with('template')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AdmissionReminderService $reminders)
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['lead', 'applicant'])],
            'subject_id' => ['required', 'integer'],
            'template_id' => ['nullable', 'exists:admission_communication_templates,id'],
            'reason' => ['required', 'string', 'max:80'],
            'channel' => ['required', 'string', 'max:40'],
            'priority' => ['nullable', 'string', 'max:40'],
            'due_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $subject = $data['subject_type'] === 'lead' ? Lead::findOrFail($data['subject_id']) : Applicant::findOrFail($data['subject_id']);
        $reminders->schedule($subject, $data, $request->user());

        return back()->with('success', 'Reminder scheduled.');
    }

    public function send(AdmissionReminderSchedule $reminder, Request $request, AdmissionReminderService $reminders)
    {
        $reminders->sendNow($reminder, $request->user());

        return back()->with('success', 'Reminder queued through communication hub.');
    }

    public function complete(AdmissionReminderSchedule $reminder, Request $request, AdmissionReminderService $reminders)
    {
        $reminders->complete($reminder, $request->user());

        return back()->with('success', 'Reminder completed.');
    }

    public function pause(AdmissionReminderSchedule $reminder, Request $request, AdmissionReminderService $reminders)
    {
        $reminders->pause($reminder, $request->input('reason', 'Paused by staff'), $request->user());

        return back()->with('success', 'Reminder paused.');
    }

    public function resume(AdmissionReminderSchedule $reminder, Request $request, AdmissionReminderService $reminders)
    {
        $reminders->resume($reminder, $request->user());

        return back()->with('success', 'Reminder resumed.');
    }

    public function cadence(Request $request, AdmissionCadenceService $cadences)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'target_type' => ['required', 'string', 'max:40'],
            'reason' => ['required', 'string', 'max:80'],
            'channel' => ['required', 'string', 'max:40'],
            'template_id' => ['nullable', 'exists:admission_communication_templates,id'],
            'initial_delay_hours' => ['nullable', 'integer', 'min:1'],
            'interval_hours' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        AdmissionCadenceRule::create([
            'name' => $data['name'],
            'target_type' => $data['target_type'],
            'reason' => $data['reason'],
            'channel' => $data['channel'],
            'template_id' => $data['template_id'] ?? null,
            'repeat_rule' => [
                'initial_delay_hours' => $data['initial_delay_hours'] ?? 24,
                'interval_hours' => $data['interval_hours'] ?? 24,
            ],
            'max_attempts' => $data['max_attempts'] ?? 3,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Cadence rule created.');
    }
}
