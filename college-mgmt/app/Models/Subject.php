<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['department_id', 'program_id', 'term_number', 'name', 'code', 'description', 'credits', 'type', 'hours_per_week', 'is_active'];

    public function department() { return $this->belongsTo(Department::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function timetableEntries() { return $this->hasMany(TimetableEntry::class); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function exams() { return $this->hasMany(Exam::class); }
    public function courseOutcomes() { return $this->hasMany(CourseOutcome::class); }
}
