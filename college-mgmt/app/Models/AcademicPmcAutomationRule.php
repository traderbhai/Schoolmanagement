<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcAutomationRule extends Model
{
    protected $fillable = ['name', 'trigger_key', 'conditions', 'actions', 'priority', 'is_active'];

    protected $casts = ['conditions' => 'array', 'actions' => 'array', 'is_active' => 'boolean'];
}
