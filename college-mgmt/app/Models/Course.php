<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['department_id', 'name', 'code', 'description', 'duration_years', 'total_semesters', 'is_active'];

    public function department() { return $this->belongsTo(Department::class); }
    public function students() { return $this->hasMany(Student::class); }
    public function timetableEntries() { return $this->hasMany(TimetableEntry::class); }
    public function feeStructures() { return $this->hasMany(FeeStructure::class); }
}
