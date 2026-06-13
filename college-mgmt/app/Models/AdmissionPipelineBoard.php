<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionPipelineBoard extends Model
{
    protected $fillable = ['name', 'object_type', 'columns', 'filters', 'is_default', 'created_by'];

    protected $casts = ['columns' => 'array', 'filters' => 'array', 'is_default' => 'boolean'];
}
