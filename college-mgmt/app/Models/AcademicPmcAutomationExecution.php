<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcAutomationExecution extends Model
{
    protected $fillable = ['rule_id', 'subject_type', 'subject_key', 'idempotency_key', 'status', 'result', 'metadata', 'executed_at'];

    protected $casts = ['metadata' => 'array', 'executed_at' => 'datetime'];

    public function rule() { return $this->belongsTo(AcademicPmcAutomationRule::class, 'rule_id'); }
}
