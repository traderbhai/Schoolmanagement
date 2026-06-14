<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcPolicyAudit extends Model
{
    protected $fillable = ['route_name', 'method', 'required_scope', 'risk_level', 'middleware_present', 'policy_present', 'last_test_status', 'missing_enforcement', 'roles_tested', 'metadata'];

    protected $casts = ['middleware_present' => 'boolean', 'policy_present' => 'boolean', 'missing_enforcement' => 'boolean', 'roles_tested' => 'array', 'metadata' => 'array'];
}
