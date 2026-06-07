<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGrievance extends Model
{
    protected $fillable = [
        'student_id', 'category', 'subject', 'description',
        'status', 'priority', 'assigned_to', 'resolution', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function student()    { return $this->belongsTo(Student::class); }
    public function assignedTo() { return $this->belongsTo(User::class, 'assigned_to'); }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'open'        => '<span class="badge bg-danger">Open</span>',
            'in_progress' => '<span class="badge bg-warning text-dark">In Progress</span>',
            'resolved'    => '<span class="badge bg-success">Resolved</span>',
            'closed'      => '<span class="badge bg-secondary">Closed</span>',
            default       => '<span class="badge bg-secondary">' . ucfirst($this->status) . '</span>',
        };
    }

    public function getPriorityBadgeAttribute(): string
    {
        return match($this->priority) {
            'urgent' => '<span class="badge bg-danger">Urgent</span>',
            'high'   => '<span class="badge bg-warning text-dark">High</span>',
            'normal' => '<span class="badge bg-primary">Normal</span>',
            'low'    => '<span class="badge bg-secondary">Low</span>',
            default  => '<span class="badge bg-secondary">' . ucfirst($this->priority) . '</span>',
        };
    }
}
