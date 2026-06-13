<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionExportLog extends Model
{
    protected $fillable = ['export_type', 'surface', 'filters', 'row_count', 'created_by'];
    protected $casts = ['filters' => 'array'];
}
