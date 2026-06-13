<?php

namespace App\Services;

use App\Models\AdmissionCallLog;
use App\Models\AdmissionReminderSchedule;
use App\Models\AdmissionScriptTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdmissionCallAttemptService
{
    public function record(Model $subject, User $caller, array $data): AdmissionCallLog
    {
        $attempt = DB::table('admission_call_attempts')
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->id)
            ->count() + 1;

        DB::table('admission_call_attempts')->insert([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'caller_user_id' => $caller->id,
            'attempt_number' => $attempt,
            'disposition' => $data['disposition'] ?? 'connected',
            'outcome' => $data['outcome'] ?? null,
            'attempted_at' => now(),
            'retry_due_at' => $data['retry_due_at'] ?? null,
            'final_attempt' => (bool) ($data['final_attempt'] ?? false),
            'notes' => $data['notes'] ?? null,
            'metadata' => json_encode(['v' => '0.038']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $phone = $subject instanceof Lead
            ? $subject->phone
            : ($subject->personal_data['phone'] ?? $subject->user?->phone ?? null);

        $callLog = AdmissionCallLog::create([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'caller_user_id' => $caller->id,
            'phone' => $phone,
            'disposition' => $data['disposition'] ?? 'connected',
            'outcome_reason' => $data['outcome'] ?? null,
            'duration_seconds' => (int) ($data['duration_seconds'] ?? 0),
            'called_at' => now(),
            'next_followup_at' => $data['retry_due_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => ['attempt_number' => $attempt, 'source' => 'calling_desk'],
        ]);

        if (! empty($data['script_template_id'])) {
            $script = AdmissionScriptTemplate::find($data['script_template_id']);
            if ($script) {
                $results = $data['script_results'] ?? array_fill(0, count($script->steps ?? []), 'covered');
                app(AdmissionScriptComplianceService::class)->log($callLog, $script, $results, $caller);
            }
        }

        if (! empty($data['retry_due_at'])) {
            AdmissionReminderSchedule::create([
                'subject_type' => get_class($subject),
                'subject_id' => $subject->id,
                'owner_user_id' => $subject->owner_user_id ?? $caller->id,
                'assigned_to' => $subject->assigned_to ?? $caller->id,
                'target' => $subject instanceof Lead ? 'lead' : 'applicant',
                'reason' => 'callback_retry',
                'channel' => 'call',
                'status' => 'scheduled',
                'priority' => $subject->priority ?? 'normal',
                'due_at' => $data['retry_due_at'],
                'notes' => 'Retry after ' . ($data['disposition'] ?? 'call attempt'),
                'metadata' => ['source' => 'calling_desk', 'attempt_number' => $attempt],
            ]);
        }

        $subject->forceFill([
            'last_activity_at' => now(),
            'last_contacted_at' => $subject instanceof Lead ? now() : ($subject->last_contacted_at ?? null),
            'next_action' => $data['next_action'] ?? ($data['retry_due_at'] ? 'Callback scheduled' : 'Review call outcome'),
        ])->save();

        return $callLog;
    }
}
