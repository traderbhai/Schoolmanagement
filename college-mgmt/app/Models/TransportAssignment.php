<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportAssignment extends Model
{
    protected $fillable = [
        'student_id', 'transport_route_id', 'transport_stop_id', 'transport_vehicle_id',
        'start_date', 'end_date', 'monthly_fee', 'status', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'monthly_fee' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function stop()
    {
        return $this->belongsTo(TransportStop::class, 'transport_stop_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(TransportVehicle::class, 'transport_vehicle_id');
    }
}
