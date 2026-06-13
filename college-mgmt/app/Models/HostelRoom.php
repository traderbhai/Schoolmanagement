<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HostelRoom extends Model
{
    protected $fillable = [
        'hostel_block_id', 'room_number', 'floor', 'room_type', 'capacity',
        'monthly_fee', 'amenities', 'status',
    ];

    protected $casts = [
        'amenities' => 'array',
    ];

    public function block()
    {
        return $this->belongsTo(HostelBlock::class, 'hostel_block_id');
    }

    public function allocations()
    {
        return $this->hasMany(HostelAllocation::class);
    }

    public function activeAllocation()
    {
        return $this->hasOne(HostelAllocation::class)->where('status', 'active');
    }
}
