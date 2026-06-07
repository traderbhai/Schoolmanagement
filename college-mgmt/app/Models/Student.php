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
        'current_term_id', 'status', 'photo', 'mentor_id',
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
    public function transcripts() { return $this->hasMany(AcademicTranscript::class); }
    public function mentor() { return $this->belongsTo(User::class, 'mentor_id'); }
    public function mentorMeetings() { return $this->hasMany(MentorMeeting::class); }
    public function leaveApplications() { return $this->hasMany(LeaveApplication::class); }
    public function subjectEnrollments() { return $this->hasMany(StudentSubjectEnrollment::class); }
    public function subjects() { return $this->belongsToMany(Subject::class, 'student_subject_enrollments')->wherePivot('status', 'active'); }

    public function calculateCGPA(): float
    {
        $avg = $this->examResults()->avg('marks_obtained');
        return round($avg ?? 0, 2);
    }

    public function calculateAttendancePercentage(): float
    {
        $total = $this->attendances()->count();
        if ($total === 0) return 0.0;
        $present = $this->attendances()->where('status', 'present')->count();
        return round(($present / $total) * 100, 2);
    }
}
