<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAutomationSchedule extends Model
{
    protected $fillable = ['automation_id', 'trigger_window', 'next_run_at', 'last_run_at', 'is_active', 'metadata'];
    protected $casts = ['next_run_at' => 'datetime', 'last_run_at' => 'datetime', 'is_active' => 'boolean', 'metadata' => 'array'];
    public function automation() { return $this->belongsTo(AdmissionAutomation::class, 'automation_id'); }
}
