<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HostelAllocation extends Model
{
    protected $fillable = [
        'hostel_room_id', 'student_id', 'bed_number', 'allocated_from', 'allocated_to',
        'status', 'allocated_by', 'vacated_at', 'vacate_reason',
    ];

    protected $casts = [
        'allocated_from' => 'date',
        'allocated_to'   => 'date',
        'vacated_at'     => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(HostelRoom::class, 'hostel_room_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function allocatedBy()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
