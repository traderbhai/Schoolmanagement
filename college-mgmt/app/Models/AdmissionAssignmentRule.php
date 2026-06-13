<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAssignmentRule extends Model
{
    protected $fillable = [
        'name', 'object_type', 'priority', 'is_active', 'conditions',
        'assignee_strategy', 'target_user_id', 'target_team_id',
        'target_role_id', 'fallback_strategy', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'conditions' => 'array',
        'metadata' => 'array',
    ];

    public function targetUser() { return $this->belongsTo(User::class, 'target_user_id'); }
    public function targetTeam() { return $this->belongsTo(DepartmentTeam::class, 'target_team_id'); }
    public function targetRole() { return $this->belongsTo(DepartmentRole::class, 'target_role_id'); }
}
