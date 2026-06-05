<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionApplicant extends Model
{
    protected $fillable = [
        'selection_session_id', 'applicant_id', 'assigned_at',
        'attendance_status', 'panel_number',
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
