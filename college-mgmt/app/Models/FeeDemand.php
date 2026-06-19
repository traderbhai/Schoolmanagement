<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeDemand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id', 'term_id', 'total_amount', 'scholarship_deduction',
        'final_amount', 'due_date', 'penalty_amount', 'last_reminder_sent', 'status',
        'cancelled_at', 'cancelled_by', 'cancellation_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'scholarship_deduction' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'due_date' => 'date',
        'penalty_amount' => 'decimal:2',
        'last_reminder_sent' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function student() { return $this->belongsTo(Student::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function paymentRequests() { return $this->hasMany(FeePaymentRequest::class); }
    public function installments() { return $this->hasMany(FeeInstallment::class); }

    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && !$this->isPaid();
    }

    public function isPaid(): bool
    {
        return $this->status === 'fully_paid';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partially_paid';
    }

    public function markAsFullyPaid(): void
    {
        $this->update(['status' => 'fully_paid']);
    }

    public function openBalance(): float
    {
        return max(0, (float) $this->final_amount + (float) $this->penalty_amount);
    }

    public function hasFinancialActivity(): bool
    {
        return $this->paymentRequests()->exists() || $this->installments()->exists();
    }

    public function calculateWithScholarship(float $totalAmount, float $scholarshipAmount = 0): void
    {
        $deduction = min($scholarshipAmount, $totalAmount);
        $this->update([
            'total_amount' => $totalAmount,
            'scholarship_deduction' => $deduction,
            'final_amount' => $totalAmount - $deduction,
        ]);
    }
}
