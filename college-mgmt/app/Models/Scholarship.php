<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Scholarship extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'name', 'description', 'percentage', 'fixed_amount',
        'type', 'status', 'valid_from', 'valid_to',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function student() { return $this->belongsTo(Student::class); }

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->valid_from && now()->isBefore($this->valid_from)) return false;
        if ($this->valid_to && now()->isAfter($this->valid_to)) return false;
        return true;
    }

    public function isExpired(): bool
    {
        return $this->valid_to && now()->isAfter($this->valid_to);
    }

    public function getDiscountAmount(float $amount): float
    {
        if (!$this->isActive()) return 0;
        $discount = 0;
        if ($this->percentage > 0) {
            $discount += $amount * ($this->percentage / 100);
        }
        if ($this->fixed_amount > 0) {
            $discount += $this->fixed_amount;
        }
        return min($discount, $amount);
    }
}
