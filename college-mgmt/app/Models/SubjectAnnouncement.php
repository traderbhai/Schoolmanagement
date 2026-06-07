<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubjectAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = ['subject_id', 'posted_by', 'term_id', 'title', 'body', 'attachment_path', 'is_pinned'];
    protected $casts = ['is_pinned' => 'boolean'];

    public function subject() { return $this->belongsTo(Subject::class); }
    public function poster() { return $this->belongsTo(User::class, 'posted_by'); }
    public function term() { return $this->belongsTo(Term::class); }
}
