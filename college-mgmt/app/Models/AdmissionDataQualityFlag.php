<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionDataQualityFlag extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'flag_type', 'severity', 'message',
        'status', 'confidence', 'metadata', 'resolved_by', 'resolved_at',
    ];

    protected $casts = ['metadata' => 'array', 'confidence' => 'float', 'resolved_at' => 'datetime'];

    public function subject() { return $this->morphTo(); }
}
