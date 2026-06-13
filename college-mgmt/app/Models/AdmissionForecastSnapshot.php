<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionForecastSnapshot extends Model
{
    protected $fillable = [
        'program_id', 'batch_id', 'source', 'target_seats', 'lead_count',
        'application_count', 'selection_count', 'offer_count', 'enrollment_count',
        'expected_conversion_rate', 'projected_enrollments', 'projected_gap',
        'metadata', 'created_by',
    ];

    protected $casts = ['metadata' => 'array', 'expected_conversion_rate' => 'float'];
}
