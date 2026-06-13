<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionPaymentGatewayEvent extends Model
{
    protected $fillable = [
        'provider', 'event_id', 'gateway_order_id', 'gateway_payment_id',
        'event_type', 'payload', 'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
