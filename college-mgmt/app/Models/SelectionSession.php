<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelectionSession extends Model
{
    protected $fillable = [
        'selection_process_step_id', 'program_id', 'batch_id', 'session_name',
        'scheduled_date', 'start_time', 'end_time', 'venue', 'max_candidates',
        'instructions', 'status', 'conducted_by', 'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function step()
    {
        return $this->belongsTo(SelectionProcessStep::class, 'selection_process_step_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function conductedBy()
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessionApplicants()
    {
        return $this->hasMany(SessionApplicant::class);
    }

    public function applicants()
    {
        return $this->belongsToMany(Applicant::class, 'session_applicants')
            ->withPivot(['assigned_at', 'attendance_status', 'panel_number'])
            ->withTimestamps();
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'scheduled'  => 'badge bg-primary',
            'ongoing'    => 'badge bg-warning text-dark',
            'completed'  => 'badge bg-success',
            'cancelled'  => 'badge bg-danger',
            default      => 'badge bg-secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'scheduled'  => 'Scheduled',
            'ongoing'    => 'Ongoing',
            'completed'  => 'Completed',
            'cancelled'  => 'Cancelled',
            default      => ucfirst($this->status),
        };
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_date', '>=', today())->orderBy('scheduled_date')->orderBy('start_time');
    }
}
