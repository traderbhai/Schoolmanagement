<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanRiskThreshold extends Model
{
    protected $fillable = ['dimension', 'scope_type', 'scope_id', 'medium_threshold', 'high_threshold', 'critical_threshold', 'is_active', 'metadata'];

    protected $casts = ['is_active' => 'boolean', 'metadata' => 'array'];
}
