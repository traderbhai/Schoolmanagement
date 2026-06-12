<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeeInstallmentPlan extends Model
{
    use HasFactory;

    protected $fillable = ['program_id', 'term_id', 'name', 'installments_count', 'late_fee_per_day', 'grace_period_days', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'late_fee_per_day' => 'decimal:2'];

    public function program() { return $this->belongsTo(Program::class); }
    public function term()    { return $this->belongsTo(Term::class); }
    public function installments() { return $this->hasMany(FeeInstallment::class, 'plan_id'); }
}
