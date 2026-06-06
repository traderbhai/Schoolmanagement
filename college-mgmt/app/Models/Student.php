<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Student extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'department_id', 'course_id', 'program_id', 'batch_id', 'specialization_id',
        'enrollment_number', 'roll_number', 'date_of_birth', 'gender', 'phone', 'address',
        'guardian_name', 'guardian_phone', 'admission_date', 'current_semester', 'current_term',
        'current_term_id', 'status', 'photo',
    ];
    protected $casts = ['date_of_birth' => 'date', 'admission_date' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function specialization() { return $this->belongsTo(Specialization::class); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function attendances() { return $this->hasMany(Attendance::class); }
    public function feePayments() { return $this->hasMany(FeePayment::class); }
    public function examResults() { return $this->hasMany(ExamResult::class); }
    public function parents() { return $this->belongsToMany(ParentProfile::class, 'parent_student', 'student_id', 'parent_id'); }
    public function currentTerm() { return $this->belongsTo(Term::class, 'current_term_id'); }
    public function scholarships() { return $this->hasMany(Scholarship::class); }
    public function termPromotions() { return $this->hasMany(TermPromotion::class); }
    public function feeDemands() { return $this->hasMany(FeeDemand::class); }
}
