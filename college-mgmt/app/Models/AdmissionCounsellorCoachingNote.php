<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionCounsellorCoachingNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'counsellor_user_id', 'reviewer_user_id', 'review_type', 'score_band',
        'strengths', 'improvement_areas', 'action_plan', 'reviewed_for_date',
        'next_review_at', 'status', 'metadata',
    ];

    protected $casts = [
        'reviewed_for_date' => 'date',
        'next_review_at' => 'date',
        'metadata' => 'array',
    ];

    public function counsellor()
    {
        return $this->belongsTo(User::class, 'counsellor_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
