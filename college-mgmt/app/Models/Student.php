<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'user_id', 'department_id', 'course_id', 'enrollment_number', 'roll_number',
        'date_of_birth', 'gender', 'phone', 'address', 'guardian_name', 'guardian_phone',
        'admission_date', 'current_semester', 'status', 'photo',
    ];
    protected $casts = ['date_of_birth' => 'date', 'admission_date' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function attendances() { return $this->hasMany(Attendance::class); }
    public function feePayments() { return $this->hasMany(FeePayment::class); }
    public function examResults() { return $this->hasMany(ExamResult::class); }
}
