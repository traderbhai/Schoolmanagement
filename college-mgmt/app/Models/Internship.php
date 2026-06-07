<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Internship extends Model
{
    protected $fillable = [
        'student_id', 'company_id', 'company_name', 'role_title',
        'start_date', 'end_date', 'type', 'status', 'description',
        'stipend', 'supervisor_name', 'supervisor_email', 'feedback', 'rating', 'approved_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'stipend'    => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function durationDays(): ?int
    {
        return $this->end_date ? $this->start_date->diffInDays($this->end_date) : null;
    }
}
