<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionApproval extends Model
{
    protected $fillable = [
        'approvable_type', 'approvable_id', 'action', 'status', 'before',
        'after', 'reason', 'requested_by', 'approved_by', 'approved_at', 'metadata',
    ];

    protected $casts = ['before' => 'array', 'after' => 'array', 'metadata' => 'array', 'approved_at' => 'datetime'];

    public function approvable() { return $this->morphTo(); }
}
