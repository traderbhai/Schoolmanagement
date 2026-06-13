<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionWalkIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_name', 'visitor_phone', 'visitor_email', 'guardian_name', 'guardian_phone',
        'program_id', 'batch_id', 'purpose', 'assigned_counsellor_id', 'lead_id',
        'applicant_id', 'status', 'outcome', 'visited_at', 'next_followup_at',
        'notes', 'created_by',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
        'next_followup_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function counsellor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_counsellor_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
