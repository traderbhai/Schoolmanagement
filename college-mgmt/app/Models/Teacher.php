<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'user_id', 'department_id', 'employee_id', 'designation', 'qualification',
        'specialization', 'phone', 'address', 'date_of_joining', 'employment_type',
        'salary', 'status', 'photo',
    ];
    protected $casts = ['date_of_joining' => 'date', 'salary' => 'decimal:2'];

    public function user() { return $this->belongsTo(User::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function timetableEntries() { return $this->hasMany(TimetableEntry::class); }
}
