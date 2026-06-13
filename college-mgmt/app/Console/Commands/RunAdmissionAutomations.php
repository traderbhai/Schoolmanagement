<?php

namespace App\Console\Commands;

use App\Services\AdmissionAutomationSchedulerService;
use Illuminate\Console\Command;

class RunAdmissionAutomations extends Command
{
    protected $signature = 'admission:run-automations';
    protected $description = 'Run due Admission OS automation schedules safely and idempotently';

    public function handle(AdmissionAutomationSchedulerService $service): int
    {
        $result = $service->runDue();
        $this->info("Admission automations processed: {$result['schedules']} schedule(s), {$result['executions']} execution(s).");

        return self::SUCCESS;
    }
}
