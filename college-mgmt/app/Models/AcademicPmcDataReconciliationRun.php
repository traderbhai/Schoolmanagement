<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcDataReconciliationRun extends Model
{
    protected $fillable = [
        'source',
        'status',
        'repair_requested',
        'started_by',
        'started_at',
        'finished_at',
        'checks_count',
        'mismatch_count',
        'critical_count',
        'repaired_count',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'repair_requested' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
