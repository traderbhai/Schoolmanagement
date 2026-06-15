<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcDataReconciliationCheck extends Model
{
    protected $fillable = [
        'check_key',
        'check_group',
        'status',
        'severity',
        'title',
        'description',
        'expected_count',
        'actual_count',
        'mismatch_count',
        'source_type',
        'source_key',
        'recommended_action',
        'details',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'details' => 'array',
        'checked_at' => 'datetime',
    ];

    public function checker() { return $this->belongsTo(User::class, 'checked_by'); }
}
