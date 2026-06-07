<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectDiscussion extends Model {
    protected $fillable = [
        'subject_id', 'posted_by', 'term_id', 'title', 'body',
        'is_pinned', 'is_resolved', 'views',
    ];

    protected $casts = [
        'is_pinned'   => 'boolean',
        'is_resolved' => 'boolean',
    ];

    public function subject(): BelongsTo  { return $this->belongsTo(Subject::class); }
    public function author(): BelongsTo   { return $this->belongsTo(User::class, 'posted_by'); }
    public function term(): BelongsTo     { return $this->belongsTo(Term::class); }
    public function replies(): HasMany    { return $this->hasMany(SubjectDiscussionReply::class, 'discussion_id'); }
}
