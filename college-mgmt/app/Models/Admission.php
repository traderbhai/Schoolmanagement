<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admission extends Model
{
    protected $fillable = [
        'applicant_name', 'email', 'phone', 'father_name', 'mother_name',
        'date_of_birth', 'gender', 'address', 'category',
        'course_id', 'last_qualification', 'last_institution', 'last_percentage',
        'application_date', 'status', 'remarks', 'converted_student_id',
    ];

    protected $casts = ['date_of_birth' => 'date', 'application_date' => 'date'];

    public function course() { return $this->belongsTo(Course::class); }
    public function student() { return $this->belongsTo(Student::class, 'converted_student_id'); }
}
