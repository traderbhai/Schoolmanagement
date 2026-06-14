<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcExportLog extends Model
{
    protected $fillable = ['user_id', 'report_key', 'filters', 'row_count', 'exported_at', 'metadata'];

    protected $casts = ['filters' => 'array', 'exported_at' => 'datetime', 'metadata' => 'array'];
}
