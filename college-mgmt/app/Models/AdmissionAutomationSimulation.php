<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAutomationSimulation extends Model
{
    protected $fillable = ['automation_id', 'trigger', 'window_start', 'window_end', 'matched_count', 'preview_actions', 'created_by'];
    protected $casts = ['window_start' => 'datetime', 'window_end' => 'datetime', 'preview_actions' => 'array'];
}
