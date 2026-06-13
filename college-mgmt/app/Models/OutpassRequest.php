<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutpassRequest extends Model
{
    protected $fillable = [
        'student_id', 'hostel_allocation_id', 'reason', 'out_datetime', 'expected_return',
        'actual_return', 'status', 'approved_by', 'approved_at', 'remarks',
    ];

    protected $casts = [
        'out_datetime'    => 'datetime',
        'expected_return' => 'datetime',
        'actual_return'   => 'datetime',
        'approved_at'     => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function allocation()
    {
        return $this->belongsTo(HostelAllocation::class, 'hostel_allocation_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
