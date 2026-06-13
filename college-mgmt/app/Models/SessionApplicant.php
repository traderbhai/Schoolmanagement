<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionApplicant extends Model
{
    protected $fillable = [
        'selection_session_id', 'applicant_id', 'assigned_at',
        'attendance_status', 'lifecycle_status', 'checked_in_at', 'completed_at', 'panel_number',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(SelectionSession::class, 'selection_session_id');
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}
