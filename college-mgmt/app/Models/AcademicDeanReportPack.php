<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanReportPack extends Model
{
    protected $fillable = ['name', 'pack_type', 'schedule', 'status', 'last_generated_at', 'filters', 'metadata'];

    protected $casts = ['last_generated_at' => 'datetime', 'filters' => 'array', 'metadata' => 'array'];
}
