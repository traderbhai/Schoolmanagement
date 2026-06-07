<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectDiscussionReply extends Model {
    protected $fillable = ['discussion_id', 'posted_by', 'body', 'is_accepted_answer'];

    protected $casts = ['is_accepted_answer' => 'boolean'];

    public function discussion(): BelongsTo { return $this->belongsTo(SubjectDiscussion::class); }
    public function author(): BelongsTo     { return $this->belongsTo(User::class, 'posted_by'); }
}
