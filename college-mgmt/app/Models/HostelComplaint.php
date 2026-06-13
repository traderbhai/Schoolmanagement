<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HostelComplaint extends Model
{
    protected $fillable = [
        'student_id', 'hostel_room_id', 'hostel_block_id', 'title', 'description',
        'category', 'priority', 'status', 'assigned_to', 'resolved_at', 'resolution_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function room()
    {
        return $this->belongsTo(HostelRoom::class, 'hostel_room_id');
    }

    public function block()
    {
        return $this->belongsTo(HostelBlock::class, 'hostel_block_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
