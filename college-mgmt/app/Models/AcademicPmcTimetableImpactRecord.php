<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableImpactRecord extends Model
{
    protected $fillable = ['change_request_id', 'impact_type', 'title', 'affected_count', 'affected_records', 'metadata'];
    protected $casts = ['affected_records' => 'array', 'metadata' => 'array'];
}
