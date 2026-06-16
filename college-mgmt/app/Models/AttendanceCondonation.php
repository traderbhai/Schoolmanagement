<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceCondonation extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'subject_id', 'term_id', 'reason',
        'sessions_requested', 'sessions_condoned', 'status', 'approved_by', 'remarks', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function student() { return $this->belongsTo(Student::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
