<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportVehicle extends Model
{
    protected $fillable = [
        'registration_number', 'vehicle_type', 'capacity', 'driver_name',
        'driver_phone', 'attendant_name', 'status',
    ];

    public function assignments()
    {
        return $this->hasMany(TransportAssignment::class);
    }
}
