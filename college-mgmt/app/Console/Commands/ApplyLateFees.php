<?php

namespace App\Console\Commands;

use App\Services\LateFeeService;
use Illuminate\Console\Command;

class ApplyLateFees extends Command
{
    protected $signature   = 'fees:apply-late-fees';
    protected $description = 'Mark overdue fee demands and apply per-day late fee penalties';

    public function handle(LateFeeService $service): int
    {
        $overdue = $service->markOverdue();
        $updated = $service->applyAccruedLateFees();

        $this->info("Marked {$overdue} demands as overdue.");
        $this->info("Updated late fees on {$updated} demands.");
        return Command::SUCCESS;
    }
}
