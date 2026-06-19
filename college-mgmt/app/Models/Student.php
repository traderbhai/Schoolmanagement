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
    public function examRegistrations() { return $this->hasMany(ExamRegistration::class); }
    public function parents() { return $this->belongsToMany(ParentProfile::class, 'parent_student', 'student_id', 'parent_id'); }
    public function currentTerm() { return $this->belongsTo(Term::class, 'current_term_id'); }
    public function scholarships() { return $this->hasMany(Scholarship::class); }
    public function termPromotions() { return $this->hasMany(TermPromotion::class); }
    public function feeDemands() { return $this->hasMany(FeeDemand::class); }
    public function transcripts() { return $this->hasMany(AcademicTranscript::class); }
    public function alumniProfile() { return $this->hasOne(AlumniProfile::class); }
    public function mentor() { return $this->belongsTo(User::class, 'mentor_id'); }
    public function mentorMeetings() { return $this->hasMany(MentorMeeting::class); }
    public function leaveApplications() { return $this->hasMany(LeaveApplication::class); }
    public function subjectEnrollments() { return $this->hasMany(StudentSubjectEnrollment::class); }
    public function subjects() { return $this->belongsToMany(Subject::class, 'student_subject_enrollments')->wherePivot('status', 'active'); }
    public function transportAssignments() { return $this->hasMany(TransportAssignment::class); }
    public function activeTransportAssignment() { return $this->hasOne(TransportAssignment::class)->where('status', 'active'); }

    public function calculateCGPA(): float
    {
        $avg = $this->examResults()
            ->where('is_absent', false)
            ->whereHas('exam', fn ($query) => $query->whereNotNull('published_at'))
            ->avg('marks_obtained');
        return round($avg ?? 0, 2);
    }

    public function calculateAttendancePercentage(): float
    {
        $subjectIds = $this->publishedAttendanceSubjectIds();
        if ($subjectIds === []) return 0.0;

        $query = $this->attendances()
            ->whereHas('timetableEntry', function ($query) use ($subjectIds) {
                $query->whereIn('subject_id', $subjectIds)
                    ->where('is_active', true)
                    ->where('status', 'published')
                    ->where(function ($versionQuery) {
                        $versionQuery->whereNull('timetable_version_id')
                            ->orWhereHas('version', fn ($version) => $version->where('status', 'published'));
                    })
                    ->when($this->program_id, fn ($scope) => $scope->where(function ($programScope) {
                        $programScope->whereNull('program_id')->orWhere('program_id', $this->program_id);
                    }))
                    ->when($this->batch_id, fn ($scope) => $scope->where(function ($batchScope) {
                        $batchScope->whereNull('batch_id')->orWhere('batch_id', $this->batch_id);
                    }))
                    ->when($this->current_term_id, fn ($scope) => $scope->where(function ($termScope) {
                        $termScope->whereNull('term_id')->orWhere('term_id', $this->current_term_id);
                    }));
            });

        $total = (clone $query)->count();
        if ($total === 0) return 0.0;
        $present = (clone $query)->whereIn('status', ['present', 'late'])->count();
        return round(($present / $total) * 100, 2);
    }

    private function publishedAttendanceSubjectIds(): array
    {
        return StudentSubjectEnrollment::where('student_id', $this->id)
            ->where('status', 'active')
            ->when($this->current_term_id, fn ($query) => $query->where('term_id', $this->current_term_id))
            ->pluck('subject_id')
            ->merge(Enrollment::where('student_id', $this->id)
                ->whereIn('status', ['active', 'enrolled'])
                ->when($this->current_term_id, fn ($query) => $query->where('term_id', $this->current_term_id))
                ->pluck('subject_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
