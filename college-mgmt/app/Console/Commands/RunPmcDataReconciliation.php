<?php

namespace App\Console\Commands;

use App\Models\AcademicPmcDataReconciliationCheck;
use App\Models\AcademicPmcDataReconciliationRun;
use App\Models\User;
use App\Services\AcademicPmcTimetableV041Service;
use Illuminate\Console\Command;
use Throwable;

class RunPmcDataReconciliation extends Command
{
    protected $signature = 'academics:pmc-reconcile-data {--repair : Run safe repairs for mismatched reconciliation checks after refresh}';
    protected $description = 'Refresh PMC data reconciliation checks and optionally repair safe data drift.';

    public function handle(AcademicPmcTimetableV041Service $service): int
    {
        $actor = $this->systemActor();
        if (! $actor) {
            $this->error('No admin, Dean, or PMC head user is available to run PMC reconciliation.');
            return self::FAILURE;
        }

        $run = AcademicPmcDataReconciliationRun::create([
            'source' => 'scheduled_cli',
            'status' => 'running',
            'repair_requested' => (bool) $this->option('repair'),
            'started_by' => $actor->id,
            'started_at' => now(),
        ]);

        try {
            $result = $service->refreshDataReconciliation($actor);
            $this->info("PMC reconciliation refreshed: {$result['checks']} check(s), {$result['mismatches']} mismatch(es).");

            $repaired = 0;
            $repairMessages = [];
            if ($this->option('repair')) {
                $checks = AcademicPmcDataReconciliationCheck::where('mismatch_count', '>', 0)->get();
                foreach ($checks as $check) {
                    $repair = $service->repairDataReconciliation($actor, $check);
                    $repaired += (int) $repair['repaired'];
                    $repairMessages[] = $repair['message'];
                    $this->line($repair['message']);
                }

                $this->info("PMC reconciliation repair completed: {$repaired} record(s) repaired.");
            }

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'checks_count' => (int) $result['checks'],
                'mismatch_count' => (int) $result['mismatches'],
                'critical_count' => (int) $result['critical'],
                'repaired_count' => $repaired,
                'metadata' => ['repair_messages' => $repairMessages],
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'failure_reason' => substr($exception->getMessage(), 0, 255),
                'metadata' => ['exception' => get_class($exception)],
            ]);

            throw $exception;
        }
    }

    private function systemActor(): ?User
    {
        return User::where('email', 'admin@college.com')->first()
            ?: User::where('email', 'admin@demo.edu')->first()
            ?: User::whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'dean_academics', 'academic_department_owner', 'pmc_head']))->first()
            ?: User::first();
    }
}
