<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApplicationWindow extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id', 'batch_id', 'opens_at', 'closes_at',
        'capacity_limit', 'current_applications', 'is_active', 'description',
    ];

    protected $casts = [
        'opens_at'              => 'datetime',
        'closes_at'             => 'datetime',
        'is_active'             => 'boolean',
        'current_applications'  => 'integer',
        'capacity_limit'        => 'integer',
    ];

    public function program()    { return $this->belongsTo(Program::class); }
    public function batch()      { return $this->belongsTo(Batch::class); }

    public function isOpen(): bool          { return now()->isBetween($this->opens_at, $this->closes_at); }
    public function isClosed(): bool        { return now()->isAfter($this->closes_at); }
    public function isNotYetOpen(): bool    { return now()->isBefore($this->opens_at); }
    public function isAtCapacity(): bool    { return $this->capacity_limit && $this->current_applications >= $this->capacity_limit; }
    public function canAcceptApplication(): bool { return $this->isOpen() && !$this->isAtCapacity() && $this->is_active; }
    public function getRemainingCapacity(): ?int { return $this->capacity_limit ? $this->capacity_limit - $this->current_applications : null; }

    public function getStatusAttribute(): string
    {
        return match(true) {
            !$this->is_active           => 'inactive',
            $this->isNotYetOpen()       => 'not_yet_open',
            $this->isClosed()           => 'closed',
            $this->isAtCapacity()       => 'full',
            $this->isOpen()             => 'open',
            default                     => 'unknown',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'inactive'      => 'badge bg-secondary',
            'not_yet_open'  => 'badge bg-info text-dark',
            'closed'        => 'badge bg-danger',
            'full'          => 'badge bg-warning text-dark',
            'open'          => 'badge bg-success',
            default         => 'badge bg-secondary',
        };
    }
}
