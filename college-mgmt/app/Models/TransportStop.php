<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportStop extends Model
{
    protected $fillable = [
        'transport_route_id', 'name', 'sequence', 'pickup_time', 'drop_time',
        'monthly_fee_override', 'is_active',
    ];

    protected $casts = [
        'monthly_fee_override' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function assignments()
    {
        return $this->hasMany(TransportAssignment::class);
    }
}
