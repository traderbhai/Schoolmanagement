<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanDecision extends Model
{
    protected $fillable = ['meeting_id', 'title', 'decision_type', 'program_id', 'batch_id', 'term_id', 'owner_user_id', 'status', 'due_at', 'evidence', 'metadata'];

    protected $casts = ['due_at' => 'datetime', 'metadata' => 'array'];
}
