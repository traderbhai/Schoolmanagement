<?php

namespace App\Services;

use App\Models\AdmissionObjectionEvent;
use App\Models\AdmissionScriptTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionCallingDeskService
{
    public function dashboard(User $user): array
    {
        $queue = app(AdmissionCallQueueSelectorService::class)->eligibleRecords($user, 40);
        $active = $queue->first()?->record;

        return [
            'active' => $active,
            'queue' => $queue,
            'script' => AdmissionScriptTemplate::where('is_active', true)->latest()->first(),
            'attempts_today' => DB::table('admission_call_attempts')->whereDate('attempted_at', today())->count(),
            'contact_rate' => $this->rate('connected'),
            'callback_due' => DB::table('admission_reminder_schedules')->where('reason', 'callback_retry')->where('due_at', '<=', now())->count(),
            'objections' => AdmissionObjectionEvent::with(['subject', 'type'])->latest()->limit(8)->get(),
            'parent_due' => DB::table('admission_parent_journeys')->where('next_due_at', '<=', now())->count(),
        ];
    }

    private function rate(string $disposition): int
    {
        $total = DB::table('admission_call_attempts')->whereDate('attempted_at', today())->count();
        if ($total === 0) {
            return 0;
        }

        $matching = DB::table('admission_call_attempts')->whereDate('attempted_at', today())->where('disposition', $disposition)->count();
        return (int) round(($matching / $total) * 100);
    }
}
