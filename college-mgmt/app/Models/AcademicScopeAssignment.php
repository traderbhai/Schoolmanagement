<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AcademicScopeAssignment extends Model
{
    protected $fillable = [
        'department_member_id',
        'user_id',
        'scope_type',
        'scope_id',
        'scope_code',
        'scope_name',
        'context',
        'can_manage',
        'is_active',
        'starts_on',
        'ends_on',
        'assigned_by',
        'assigned_at',
        'deactivated_by',
        'deactivated_at',
        'metadata',
    ];

    protected $casts = [
        'can_manage' => 'boolean',
        'is_active' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'assigned_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function member() { return $this->belongsTo(DepartmentMember::class, 'department_member_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
    public function deactivatedBy() { return $this->belongsTo(User::class, 'deactivated_by'); }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $dateQuery) {
                $dateQuery->whereNull('starts_on')->orWhere('starts_on', '<=', now()->toDateString());
            })
            ->where(function (Builder $dateQuery) {
                $dateQuery->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString());
            });
    }
}
