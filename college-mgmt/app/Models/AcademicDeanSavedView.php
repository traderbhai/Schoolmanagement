<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanSavedView extends Model
{
    protected $fillable = ['user_id', 'name', 'surface', 'filters', 'is_default'];

    protected $casts = [
        'filters' => 'array',
        'is_default' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
