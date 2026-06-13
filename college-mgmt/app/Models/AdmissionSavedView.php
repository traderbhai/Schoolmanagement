<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionSavedView extends Model
{
    protected $fillable = ['name', 'surface', 'role_name', 'user_id', 'filters', 'layout', 'is_default'];

    protected $casts = ['filters' => 'array', 'layout' => 'array', 'is_default' => 'boolean'];
}
