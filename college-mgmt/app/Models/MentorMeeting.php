<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MentorMeeting extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'mentor_id', 'meeting_date', 'topic', 'notes', 'status'];
    protected $casts = ['meeting_date' => 'date'];

    public function student() { return $this->belongsTo(Student::class); }
    public function mentor() { return $this->belongsTo(User::class, 'mentor_id'); }
}
