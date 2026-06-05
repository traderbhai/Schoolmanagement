<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CounsellingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'applicant_id', 'logged_by', 'interaction_type', 'outcome',
        'notes', 'next_followup_date', 'duration_minutes',
    ];

    protected $casts = [
        'next_followup_date' => 'date',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
