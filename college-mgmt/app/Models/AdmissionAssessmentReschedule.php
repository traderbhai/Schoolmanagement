<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAssessmentReschedule extends Model
{
    protected $fillable = [
        'assignment_id', 'applicant_id', 'from_session_id', 'to_session_id',
        'old_scheduled_at', 'new_scheduled_at', 'reason', 'status',
        'requested_by', 'metadata',
    ];

    protected $casts = [
        'old_scheduled_at' => 'datetime',
        'new_scheduled_at' => 'datetime',
        'metadata' => 'array',
    ];
}
