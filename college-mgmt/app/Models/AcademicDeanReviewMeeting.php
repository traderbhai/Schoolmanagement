<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanReviewMeeting extends Model
{
    protected $fillable = [
        'title', 'review_type', 'scheduled_for', 'chaired_by', 'scope_type',
        'scope_id', 'status', 'summary', 'metadata',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'metadata' => 'array',
    ];

    public function chair() { return $this->belongsTo(User::class, 'chaired_by'); }
    public function actions() { return $this->hasMany(AcademicDeanActionItem::class, 'meeting_id'); }
}
