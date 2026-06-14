<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanCalendarEvent extends Model
{
    protected $fillable = ['event_type', 'title', 'owner_user_id', 'program_id', 'batch_id', 'term_id', 'starts_at', 'ends_at', 'status', 'source_type', 'source_key', 'metadata'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'metadata' => 'array'];
}
