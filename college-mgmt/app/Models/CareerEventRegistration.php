<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CareerEventRegistration extends Model {
    use HasFactory;
    protected $fillable = ['career_event_id','student_id','attended','status','cancelled_at','cancelled_by'];
    protected $casts = ['attended'=>'boolean', 'cancelled_at' => 'datetime'];
    public function event() { return $this->belongsTo(CareerEvent::class,'career_event_id'); }
    public function student() { return $this->belongsTo(Student::class); }
}
