<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdmissionManagerReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'reviewable_type', 'reviewable_id', 'review_type', 'status', 'severity',
        'assigned_manager_id', 'reviewed_by', 'finding', 'action_required',
        'resolution_notes', 'due_at', 'reviewed_at', 'metadata',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_manager_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
