<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAvailability extends Model {
    protected $table = 'teacher_availability';

    protected $fillable = [
        'teacher_id','term_id','day_of_week','timetable_slot_id','availability','notes',
    ];

    public function teacher(): BelongsTo { return $this->belongsTo(Teacher::class); }
    public function term(): BelongsTo    { return $this->belongsTo(Term::class); }
    public function slot(): BelongsTo    { return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id'); }

    public static function dayName(int $day): string {
        return ['','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][$day] ?? '?';
    }
}
