<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'teacher_id', 'leave_type', 'from_date', 'to_date', 'days',
        'reason', 'description', 'attachment_path', 'status',
        'reviewed_by', 'approved_by', 'review_remarks', 'admin_remarks',
        'reviewed_at', 'approved_at',
    ];

    protected $casts = [
        'from_date'   => 'date',
        'to_date'     => 'date',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function student()  { return $this->belongsTo(Student::class); }
    public function teacher()  { return $this->belongsTo(Teacher::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }

    public function getDaysCount(): int
    {
        return $this->from_date->diffInDays($this->to_date) + 1;
    }
}
