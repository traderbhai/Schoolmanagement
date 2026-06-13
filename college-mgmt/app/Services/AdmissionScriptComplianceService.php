<?php

namespace App\Services;

use App\Models\AdmissionCallLog;
use App\Models\AdmissionScriptCompletionLog;
use App\Models\AdmissionScriptTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;

class AdmissionScriptComplianceService
{
    public function log(AdmissionCallLog $call, AdmissionScriptTemplate $template, array $stepResults, ?User $actor = null): AdmissionScriptCompletionLog
    {
        $scored = collect($stepResults)->filter(fn ($state) => $state !== 'na');
        $covered = $scored->filter(fn ($state) => $state === 'covered')->count();
        $percent = $scored->count() ? round(($covered / $scored->count()) * 100, 2) : 0;

        return AdmissionScriptCompletionLog::create([
            'script_template_id' => $template->id,
            'call_log_id' => $call->id,
            'subject_type' => $call->subject_type,
            'subject_id' => $call->subject_id,
            'counsellor_user_id' => $actor?->id ?? $call->caller_user_id,
            'step_results' => $stepResults,
            'compliance_percent' => $percent,
            'metadata' => ['v' => '0.037'],
        ]);
    }

    public function averageFor(User $user): float
    {
        return round(AdmissionScriptCompletionLog::where('counsellor_user_id', $user->id)->avg('compliance_percent') ?? 0, 2);
    }
}
