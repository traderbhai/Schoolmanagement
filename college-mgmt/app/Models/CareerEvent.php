<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CareerEvent extends Model {
    use HasFactory;
    protected $fillable = ['title','event_type','organizer_id','event_date','venue','description','seats','registration_deadline','is_published'];
    protected $casts = ['event_date'=>'date','registration_deadline'=>'date','is_published'=>'boolean'];
    public function organizer() { return $this->belongsTo(User::class,'organizer_id'); }
    public function registrations() { return $this->hasMany(CareerEventRegistration::class); }
    public function isOpen(): bool {
        return $this->is_published
            && (!$this->registration_deadline || $this->registration_deadline->isFuture())
            && (!$this->seats || $this->registrations()->count() < $this->seats);
    }
}
