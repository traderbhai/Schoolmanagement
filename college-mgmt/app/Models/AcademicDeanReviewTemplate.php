<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanReviewTemplate extends Model
{
    protected $fillable = ['name', 'review_type', 'recurrence', 'is_active', 'agenda_items', 'metadata'];

    protected $casts = ['is_active' => 'boolean', 'agenda_items' => 'array', 'metadata' => 'array'];
}
