<?php

namespace App\Console\Commands;

use App\Services\ApprovalChainService;
use Illuminate\Console\Command;

class CheckApprovalSla extends Command
{
    protected $signature   = 'approvals:check-sla';
    protected $description = 'Escalate overdue pending approvals that have breached their SLA';

    public function handle(): int
    {
        $count = ApprovalChainService::escalateOverdue();

        $this->info("Escalated {$count} overdue approval(s).");

        return Command::SUCCESS;
    }
}
