<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'from_date', 'to_date', 'reason', 'description',
        'attachment_path', 'status', 'reviewed_by', 'review_remarks', 'reviewed_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function student() { return $this->belongsTo(Student::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function getDaysCount(): int
    {
        return $this->from_date->diffInDays($this->to_date) + 1;
    }
}
