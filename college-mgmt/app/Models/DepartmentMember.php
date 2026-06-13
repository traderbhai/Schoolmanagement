<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentMember extends Model
{
    protected $fillable = [
        'department_id', 'department_role_id', 'department_team_id', 'user_id',
        'reports_to_member_id', 'scope_rules', 'is_active',
    ];

    protected $casts = [
        'scope_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function department() { return $this->belongsTo(Department::class); }
    public function role() { return $this->belongsTo(DepartmentRole::class, 'department_role_id'); }
    public function team() { return $this->belongsTo(DepartmentTeam::class, 'department_team_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function manager() { return $this->belongsTo(self::class, 'reports_to_member_id'); }
    public function directReports() { return $this->hasMany(self::class, 'reports_to_member_id'); }
}
