<?php

namespace App\Services;

use App\Models\{FeeDemand, FeeInstallment};
use Illuminate\Support\Collection;

class LateFeeService
{
    /**
     * Calculate late fee for a fee demand based on days overdue.
     * Uses demand's late_fee_rate and grace_period_days columns.
     */
    public function calculate(FeeDemand $demand): float
    {
        if ($demand->status === 'fully_paid') return 0;

        $dueDate       = $demand->due_date;
        $gracePeriod   = (int)($demand->grace_period_days ?? 0);
        $lateFeeRate   = (float)($demand->late_fee_rate ?? 0);

        if (!$dueDate || $dueDate->isFuture() || $lateFeeRate <= 0) return 0;

        $daysLate = max(0, $dueDate->diffInDays(now()) - $gracePeriod);
        return round($daysLate * $lateFeeRate, 2);
    }

    /**
     * Apply accrued late fees to all overdue demands, updating penalty_amount.
     * Meant to be run daily via scheduled command.
     */
    public function applyAccruedLateFees(): int
    {
        $updated = 0;
        FeeDemand::where('status', 'overdue')
            ->whereColumn('due_date', '<', now()->toDateString())
            ->chunkById(200, function (Collection $demands) use (&$updated) {
                foreach ($demands as $demand) {
                    $newPenalty = $this->calculate($demand);
                    if ((float)$demand->penalty_amount !== $newPenalty) {
                        $demand->update(['penalty_amount' => $newPenalty]);
                        $updated++;
                    }
                }
            });
        return $updated;
    }

    /**
     * Mark overdue demands: pending demands past due_date + grace_period_days.
     */
    public function markOverdue(): int
    {
        return FeeDemand::where('status', 'pending')
            ->whereRaw("date(due_date) < date('now')")
            ->update(['status' => 'overdue']);
    }

    /**
     * Generate installment schedule for a demand.
     */
    public function generateInstallments(FeeDemand $demand, int $count, int $intervalDays = 30, ?int $planId = null): array
    {
        $installments = [];
        $amountEach = round($demand->final_amount / $count, 2);
        $remainder  = $demand->final_amount - ($amountEach * ($count - 1));

        for ($i = 1; $i <= $count; $i++) {
            $installments[] = FeeInstallment::create([
                'fee_demand_id'       => $demand->id,
                'plan_id'             => $planId,
                'installment_number'  => $i,
                'amount'              => $i === $count ? $remainder : $amountEach,
                'due_date'            => $demand->due_date->addDays(($i - 1) * $intervalDays)->toDateString(),
                'status'              => 'pending',
            ]);
        }

        return $installments;
    }
}
