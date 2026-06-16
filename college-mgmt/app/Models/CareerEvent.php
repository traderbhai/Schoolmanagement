<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CareerEvent extends Model {
    use HasFactory;
    public const TYPE_LABELS = [
        'seminar' => 'Seminar',
        'mock_interview' => 'Mock Interview',
        'workshop' => 'Workshop',
        'company_visit' => 'Company Visit',
        'career_fair' => 'Career Fair',
        'other' => 'Other',
    ];

    protected $fillable = ['title','event_type','organizer_id','event_date','venue','description','seats','registration_deadline','is_published'];
    protected $casts = ['event_date'=>'date','registration_deadline'=>'date','is_published'=>'boolean'];
    public function organizer() { return $this->belongsTo(User::class,'organizer_id'); }
    public function registrations() { return $this->hasMany(CareerEventRegistration::class); }
    public function isOpen(): bool {
        return $this->is_published
            && (!$this->event_date || $this->event_date->isToday() || $this->event_date->isFuture())
            && (!$this->registration_deadline || $this->registration_deadline->isToday() || $this->registration_deadline->isFuture())
            && (!$this->seats || $this->registrations()->count() < $this->seats);
    }
}
