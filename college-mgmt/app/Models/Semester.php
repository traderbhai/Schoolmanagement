<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Semester extends Model
{
    use HasFactory;
    protected $fillable = ['academic_year_id', 'name', 'number', 'start_date', 'end_date', 'is_current'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean'];

    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function timetableEntries() { return $this->hasMany(TimetableEntry::class); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function exams() { return $this->hasMany(Exam::class); }

    public static function current() { return static::where('is_current', true)->first(); }
}
