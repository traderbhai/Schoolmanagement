<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CareerEventRegistration extends Model {
    use HasFactory;
    protected $fillable = ['career_event_id','student_id','attended'];
    protected $casts = ['attended'=>'boolean'];
    public function event() { return $this->belongsTo(CareerEvent::class,'career_event_id'); }
    public function student() { return $this->belongsTo(Student::class); }
}
