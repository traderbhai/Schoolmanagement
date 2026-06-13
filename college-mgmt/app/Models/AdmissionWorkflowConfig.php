<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionWorkflowConfig extends Model
{
    protected $fillable = ['type', 'key', 'label', 'sort_order', 'is_active', 'config'];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];
}
