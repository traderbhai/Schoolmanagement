<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TimetableSlot extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'start_time', 'end_time', 'is_break', 'sort_order', 'is_active'];
    protected $casts = ['is_break' => 'boolean', 'is_active' => 'boolean'];

    public function entries() { return $this->hasMany(TimetableEntry::class); }
    public function lockedSlots() { return $this->hasMany(AcademicPmcLockedSlot::class, 'timetable_slot_id'); }
    public function generationItems() { return $this->hasMany(AcademicPmcTimetableGenerationItem::class, 'timetable_slot_id'); }
    public function sessionDeliveryLogs() { return $this->hasMany(AcademicPmcSessionDeliveryLog::class, 'timetable_slot_id'); }
    public function teacherAvailabilities() { return $this->hasMany(TeacherAvailability::class, 'timetable_slot_id'); }

    public function getDurationMinutesAttribute(): int
    {
        return \Carbon\Carbon::parse($this->start_time)->diffInMinutes(\Carbon\Carbon::parse($this->end_time));
    }
}
