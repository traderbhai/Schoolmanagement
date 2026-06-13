<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionRouteAccessAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_name', 'uri', 'method', 'required_scope', 'risk_level', 'status',
        'notes', 'reviewed_by', 'reviewed_at', 'metadata',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];
}
