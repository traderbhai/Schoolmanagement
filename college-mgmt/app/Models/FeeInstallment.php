<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeeInstallment extends Model
{
    use HasFactory;

    protected $fillable = ['fee_demand_id', 'plan_id', 'installment_number', 'amount', 'due_date', 'late_fee_accrued', 'amount_paid', 'status', 'paid_at'];
    protected $casts = ['due_date' => 'date', 'paid_at' => 'date', 'amount' => 'decimal:2', 'late_fee_accrued' => 'decimal:2', 'amount_paid' => 'decimal:2'];

    public function feeDemand() { return $this->belongsTo(FeeDemand::class); }
    public function plan()      { return $this->belongsTo(FeeInstallmentPlan::class, 'plan_id'); }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date->isPast();
    }

    public function currentLateFee(): float
    {
        if (!$this->isOverdue() || !$this->plan) return 0;
        $daysLate = max(0, $this->due_date->diffInDays(now()) - ($this->plan->grace_period_days ?? 0));
        return round($daysLate * (float)$this->plan->late_fee_per_day, 2);
    }
}
