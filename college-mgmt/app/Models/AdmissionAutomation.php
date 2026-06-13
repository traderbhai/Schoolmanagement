<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAutomation extends Model
{
    protected $fillable = ['name', 'trigger', 'priority', 'is_active', 'conditions', 'actions', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'conditions' => 'array', 'actions' => 'array'];
}
