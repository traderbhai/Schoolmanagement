<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MentorMessage extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'sender_id', 'message', 'read_at'];
    protected $casts = ['read_at' => 'datetime'];

    public function student() { return $this->belongsTo(Student::class); }
    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }
}
