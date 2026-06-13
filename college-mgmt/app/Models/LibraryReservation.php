<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryReservation extends Model {
    use HasFactory;
    protected $fillable = ['book_id','student_id','teacher_id','reserved_at','expires_at','status'];
    protected $casts = ['reserved_at' => 'datetime', 'expires_at' => 'date'];

    public function book() { return $this->belongsTo(Book::class); }
    public function student() { return $this->belongsTo(Student::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
}
