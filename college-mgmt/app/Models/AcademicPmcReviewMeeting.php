<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcReviewMeeting extends Model
{
    protected $fillable = ['title', 'review_type', 'scheduled_for', 'chair_user_id', 'status', 'agenda', 'minutes', 'metadata'];

    protected $casts = ['scheduled_for' => 'datetime', 'metadata' => 'array'];

    public function chair() { return $this->belongsTo(User::class, 'chair_user_id'); }
}
