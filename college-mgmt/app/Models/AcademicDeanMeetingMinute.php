<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanMeetingMinute extends Model
{
    protected $fillable = ['meeting_id', 'minutes', 'status', 'submitted_by', 'approved_by', 'approved_at', 'metadata'];

    protected $casts = ['approved_at' => 'datetime', 'metadata' => 'array'];

    public function meeting() { return $this->belongsTo(AcademicDeanReviewMeeting::class, 'meeting_id'); }
}
