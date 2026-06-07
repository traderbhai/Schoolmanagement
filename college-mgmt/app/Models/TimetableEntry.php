<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TimetableEntry extends Model
{
    protected $fillable = [
        'semester_id', 'course_id', 'program_id', 'term_id', 'batch_id', 'subject_id', 'teacher_id',
        'classroom_id', 'timetable_slot_id', 'day_of_week', 'is_active', 'status', 'timetable_version_id',
    ];

    public function semester()        { return $this->belongsTo(Semester::class); }
    public function course()          { return $this->belongsTo(Course::class); }
    public function program()         { return $this->belongsTo(Program::class); }
    public function term()            { return $this->belongsTo(Term::class); }
    public function batch()           { return $this->belongsTo(Batch::class); }
    public function subject()         { return $this->belongsTo(Subject::class); }
    public function teacher()         { return $this->belongsTo(Teacher::class); }
    public function classroom()       { return $this->belongsTo(Classroom::class); }
    public function slot()            { return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id'); }
    public function attendances()     { return $this->hasMany(Attendance::class); }
    public function substitutions()   { return $this->hasMany(TimetableSubstitution::class); }
    public function version()         { return $this->belongsTo(TimetableVersion::class, 'timetable_version_id'); }

    public function getDayNameAttribute(): string
    {
        return ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$this->day_of_week] ?? '';
    }
}
