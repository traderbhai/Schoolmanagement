<?php

namespace App\Services;

use App\Models\AdmissionAutomationSchedule;
use App\Models\Applicant;
use App\Models\Lead;

class AdmissionAutomationSchedulerService
{
    public function __construct(private AdmissionAutomationService $automation, private AdmissionAutomationSimulationService $simulation) {}

    public function runDue(?int $limit = 50): array
    {
        $schedules = AdmissionAutomationSchedule::with('automation')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))
            ->limit($limit)
            ->get();

        $executed = 0;
        foreach ($schedules as $schedule) {
            $subjects = str_contains($schedule->automation->trigger, 'applicant')
                ? Applicant::latest()->limit(10)->get()
                : Lead::latest()->limit(10)->get();
            foreach ($subjects as $subject) {
                $this->simulation->detectConflicts($schedule->automation, $subject);
                $executed += $this->automation->run($schedule->automation->trigger, $subject)->count();
            }
            $schedule->update(['last_run_at' => now(), 'next_run_at' => now()->addDay()]);
        }

        return ['schedules' => $schedules->count(), 'executions' => $executed];
    }
}
