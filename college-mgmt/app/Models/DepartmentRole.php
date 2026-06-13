<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentRole extends Model
{
    protected $fillable = [
        'department_id', 'name', 'code', 'level', 'can_manage_lower_levels',
        'can_view_team_data', 'can_assign_work', 'permissions', 'scope_rules',
        'is_active',
    ];

    protected $casts = [
        'can_manage_lower_levels' => 'boolean',
        'can_view_team_data' => 'boolean',
        'can_assign_work' => 'boolean',
        'permissions' => 'array',
        'scope_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function department() { return $this->belongsTo(Department::class); }
    public function members() { return $this->hasMany(DepartmentMember::class); }
}
