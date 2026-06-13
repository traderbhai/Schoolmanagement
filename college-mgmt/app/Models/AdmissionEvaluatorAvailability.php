<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionEvaluatorAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'available_from', 'available_until', 'availability_type',
        'location_mode', 'notes', 'is_active', 'metadata',
    ];

    protected $casts = [
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
