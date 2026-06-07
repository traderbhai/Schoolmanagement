<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudyMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id', 'uploaded_by', 'term_id', 'timetable_entry_id',
        'title', 'description', 'type', 'file_path', 'external_url',
        'file_size_kb', 'is_published',
    ];

    protected $casts = ['is_published' => 'boolean'];

    public function subject() { return $this->belongsTo(Subject::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function term() { return $this->belongsTo(Term::class); }
    public function timetableEntry() { return $this->belongsTo(TimetableEntry::class); }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'pre_read'   => 'Pre-Read',
            'post_read'  => 'Post-Read',
            'notes'      => 'Notes',
            'reference'  => 'Reference',
            'slides'     => 'Slides',
            'video'      => 'Video',
            default      => 'Other',
        };
    }
}
