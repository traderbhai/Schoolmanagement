<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportRoute extends Model
{
    protected $fillable = [
        'name', 'code', 'start_point', 'end_point', 'distance_km', 'monthly_fee', 'is_active',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'monthly_fee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function stops()
    {
        return $this->hasMany(TransportStop::class)->orderBy('sequence');
    }

    public function assignments()
    {
        return $this->hasMany(TransportAssignment::class);
    }
}
