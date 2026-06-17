<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HostelFeeDemand extends Model
{
    protected $fillable = [
        'hostel_allocation_id', 'student_id', 'month', 'amount', 'status', 'due_date', 'paid_at',
        'paid_by', 'waived_at', 'waived_by', 'waiver_reason',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at'  => 'datetime',
        'waived_at' => 'datetime',
    ];

    public function allocation()
    {
        return $this->belongsTo(HostelAllocation::class, 'hostel_allocation_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
