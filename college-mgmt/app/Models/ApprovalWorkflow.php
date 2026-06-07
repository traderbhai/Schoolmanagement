<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApprovalWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'approver_role',
        'status',
        'remarks',
        'approver_id',
        'approved_at',
        'sla_days',
        'due_at',
        'escalated_to_role',
        'escalated_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'due_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_at !== null && $this->due_at->isPast();
    }

    public function isEscalated(): bool
    {
        return $this->escalated_at !== null;
    }

    public function slaBadgeClass(): string
    {
        if ($this->status !== 'pending' || $this->due_at === null) {
            return 'text-muted';
        }
        if ($this->due_at->isPast()) {
            return 'text-danger fw-semibold';
        }
        if ($this->due_at->diffInHours(now()) < 4) {
            return 'text-warning fw-semibold';
        }
        return 'text-success';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'pending'  => 'badge bg-warning text-dark',
            'approved' => 'badge bg-success',
            'rejected' => 'badge bg-danger',
            default    => 'badge bg-secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'  => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default    => ucfirst($this->status),
        };
    }
}
