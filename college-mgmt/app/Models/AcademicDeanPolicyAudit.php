<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanPolicyAudit extends Model
{
    protected $fillable = ['route_name', 'method', 'expected_roles', 'risk_level', 'has_policy', 'last_test_status', 'notes', 'metadata'];

    protected $casts = ['has_policy' => 'boolean', 'metadata' => 'array'];
}
