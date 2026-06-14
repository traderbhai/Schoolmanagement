<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableControl extends Model
{
    protected $fillable = ['program_id', 'batch_id', 'term_id', 'title', 'status', 'draft_slots', 'published_slots', 'teacher_conflicts', 'room_conflicts', 'freeze_due_at', 'published_at', 'metadata'];

    protected $casts = ['freeze_due_at' => 'datetime', 'published_at' => 'datetime', 'metadata' => 'array'];

    public function program() { return $this->belongsTo(Program::class); }
}
