<?php

namespace App\Console\Commands;

use App\Services\AdmissionOfferSeatSchedulerService;
use Illuminate\Console\Command;

class RunAdmissionFinalSchedulers extends Command
{
    protected $signature = 'admission:run-final-schedulers';
    protected $description = 'Run Admission v0.039 final schedulers for expired offers, seat holds, and waitlist replies.';

    public function handle(AdmissionOfferSeatSchedulerService $service): int
    {
        $result = $service->run();
        $this->info('Admission final schedulers completed: '.json_encode($result));

        return self::SUCCESS;
    }
}
