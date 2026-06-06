<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadFollowUp extends Model
{
    protected $fillable = ['lead_id', 'assigned_to', 'type', 'scheduled_at', 'completed_at', 'notes'];
    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime'];

    public function lead()       { return $this->belongsTo(Lead::class); }
    public function counsellor() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function isCompleted(): bool { return $this->completed_at !== null; }

    public function getTypeLabelAttribute(): string {
        return match($this->type) {
            'call'    => 'Phone Call',
            'email'   => 'Email',
            'meeting' => 'Meeting',
            'visit'   => 'Visit',
            default   => ucfirst($this->type),
        };
    }
    public function getTypeBadgeAttribute(): string {
        return match($this->type) {
            'call'    => 'badge bg-primary',
            'email'   => 'badge bg-info text-dark',
            'meeting' => 'badge bg-warning text-dark',
            'visit'   => 'badge bg-secondary',
            default   => 'badge bg-light text-dark',
        };
    }
}
