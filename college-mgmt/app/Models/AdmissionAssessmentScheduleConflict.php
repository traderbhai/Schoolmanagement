<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionAssessmentScheduleConflict extends Model
{
    use HasFactory;

    protected $fillable = [
        'panel_id', 'user_id', 'conflict_type', 'severity', 'status', 'message',
        'detected_at', 'resolved_by', 'resolved_at', 'metadata',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function panel()
    {
        return $this->belongsTo(AdmissionAssessmentPanel::class, 'panel_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
