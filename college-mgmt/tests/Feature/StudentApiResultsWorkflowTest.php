<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\FeePayment;
use App\Models\FeeDemand;
use App\Models\FeeStructure;
use App\Models\HostelAllocation;
use App\Models\HostelBlock;
use App\Models\HostelFeeDemand;
use App\Models\HostelRoom;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentApiResultsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_api_attendance_uses_only_published_timetable_history(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('student');
        $course = Course::factory()->create();
        $program = Program::factory()->create();
        $semester = Semester::factory()->create(['name' => 'Term 1', 'number' => 1]);
        $term = Term::factory()->create(['program_id' => $program->id, 'term_number' => 1, 'name' => 'Term 1']);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $publishedSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'API Published Attendance']);
        $draftSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'API Draft Attendance']);
        $draftVersionSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'API Draft Version Attendance']);
        $unenrolledSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'API Unenrolled Published Attendance']);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $publishedSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $publishedEntry = TimetableEntry::factory()->create([
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $publishedSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'published',
        ]);
        $draftEntry = TimetableEntry::factory()->create([
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $draftSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'draft',
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $draftVersionSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        $unenrolledEntry = TimetableEntry::factory()->create([
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $unenrolledSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'published',
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $publishedEntry->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'present',
        ]);
        foreach ([$draftEntry, $draftVersionEntry] as $entry) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $entry->id,
                'date' => now()->subDays(2)->toDateString(),
                'status' => 'absent',
            ]);
        }
        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $unenrolledEntry->id,
            'date' => now()->subDays(3)->toDateString(),
            'status' => 'absent',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/student/attendance')
            ->assertOk()
            ->assertJsonPath('overall_percentage', 100)
            ->assertJsonPath('total_classes', 1)
            ->assertJsonPath('present', 1)
            ->assertJsonFragment(['subject' => 'API Published Attendance'])
            ->assertJsonMissing(['subject' => 'API Draft Attendance'])
            ->assertJsonMissing(['subject' => 'API Draft Version Attendance'])
            ->assertJsonMissing(['subject' => 'API Unenrolled Published Attendance']);
    }

    public function test_student_api_results_return_only_published_results(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('student');
        $program = Program::factory()->create();
        $semester = Semester::factory()->create(['name' => 'Term 1', 'number' => 1]);
        $term = Term::factory()->create(['program_id' => $program->id, 'term_number' => 1, 'name' => 'Term 1']);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);

        $officialSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Published API Subject']);
        $draftSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Draft API Subject']);
        $unenrolledSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Unenrolled API Subject']);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $officialSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        $officialExam = Exam::factory()->create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $officialSubject->id,
            'published_at' => now(),
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $draftSubject->id,
            'published_at' => null,
        ]);
        $unenrolledExam = Exam::factory()->create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $unenrolledSubject->id,
            'published_at' => now(),
        ]);

        ExamResult::factory()->create([
            'exam_id' => $officialExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 81,
            'is_absent' => false,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 99,
            'is_absent' => false,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $unenrolledExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 77,
            'is_absent' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/student/results')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'subject' => 'Published API Subject',
                'marks_obtained' => '81.00',
            ])
            ->assertJsonMissing([
                'subject' => 'Draft API Subject',
            ])
            ->assertJsonMissing([
                'subject' => 'Unenrolled API Subject',
            ])
            ->assertJsonMissing([
                'marks_obtained' => '99.00',
            ])
            ->assertJsonMissing([
                'marks_obtained' => '77.00',
            ]);
    }

    public function test_student_api_fees_return_only_paid_receipt_history(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('student');
        $program = Program::factory()->create();
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        $year = AcademicYear::create([
            'name' => '2026-27',
            'start_year' => 2026,
            'end_year' => 2027,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'is_current' => true,
        ]);
        $structure = FeeStructure::create([
            'course_id' => $student->course_id,
            'academic_year_id' => $year->id,
            'fee_type' => 'Tuition',
            'amount' => 10000,
        ]);

        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'amount_paid' => 4000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'API-PAID-001',
            'payment_method' => 'online',
            'status' => 'paid',
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'amount_paid' => 6000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'API-PENDING-001',
            'payment_method' => 'online',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/student/fees')
            ->assertOk()
            ->assertJsonPath('total_due', 10000)
            ->assertJsonPath('total_paid', 4000)
            ->assertJsonPath('balance', 6000)
            ->assertJsonCount(1, 'payments')
            ->assertJsonFragment(['receipt_number' => 'API-PAID-001'])
            ->assertJsonMissing(['receipt_number' => 'API-PENDING-001']);
    }

    public function test_student_api_fee_fallback_uses_current_academic_year_only(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('student');
        $program = Program::factory()->create();
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        $oldYear = AcademicYear::create([
            'name' => '2025-26',
            'start_year' => 2025,
            'end_year' => 2026,
            'start_date' => now()->subYear()->startOfYear(),
            'end_date' => now()->subYear()->endOfYear(),
            'is_current' => false,
        ]);
        $currentYear = AcademicYear::create([
            'name' => '2026-27',
            'start_year' => 2026,
            'end_year' => 2027,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'is_current' => true,
        ]);
        $oldStructure = FeeStructure::create([
            'course_id' => $student->course_id,
            'academic_year_id' => $oldYear->id,
            'fee_type' => 'Old Tuition',
            'amount' => 25000,
        ]);
        $currentStructure = FeeStructure::create([
            'course_id' => $student->course_id,
            'academic_year_id' => $currentYear->id,
            'fee_type' => 'Current Tuition',
            'amount' => 10000,
        ]);

        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $oldStructure->id,
            'amount_paid' => 25000,
            'payment_date' => now()->subYear()->toDateString(),
            'receipt_number' => 'API-OLD-YEAR-PAID',
            'payment_method' => 'online',
            'status' => 'paid',
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $currentStructure->id,
            'amount_paid' => 4000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'API-CURRENT-YEAR-PAID',
            'payment_method' => 'online',
            'status' => 'paid',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/student/fees')
            ->assertOk()
            ->assertJsonPath('total_due', 10000)
            ->assertJsonPath('total_paid', 4000)
            ->assertJsonPath('balance', 6000)
            ->assertJsonFragment(['receipt_number' => 'API-OLD-YEAR-PAID'])
            ->assertJsonFragment(['receipt_number' => 'API-CURRENT-YEAR-PAID'])
            ->assertJsonMissing(['total_due' => 35000]);
    }

    public function test_student_api_fees_use_fee_demands_as_canonical_balance_when_available(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('student');
        $program = Program::factory()->create();
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        $year = AcademicYear::create([
            'name' => '2026-27',
            'start_year' => 2026,
            'end_year' => 2027,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'is_current' => true,
        ]);
        $structure = FeeStructure::create([
            'course_id' => $student->course_id,
            'academic_year_id' => $year->id,
            'fee_type' => 'Legacy Structure Should Not Drive API',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'total_amount' => 50000,
            'scholarship_deduction' => 5000,
            'final_amount' => 45000,
            'penalty_amount' => 1000,
            'status' => 'overdue',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'total_amount' => 20000,
            'scholarship_deduction' => 0,
            'final_amount' => 20000,
            'penalty_amount' => 9000,
            'status' => 'fully_paid',
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'amount_paid' => 20000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'API-DEMAND-PAID-001',
            'payment_method' => 'online',
            'status' => 'paid',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/student/fees')
            ->assertOk()
            ->assertJsonPath('total_due', 66000)
            ->assertJsonPath('total_paid', 20000)
            ->assertJsonPath('balance', 46000)
            ->assertJsonFragment(['receipt_number' => 'API-DEMAND-PAID-001'])
            ->assertJsonMissing(['total_due' => 999999]);
    }

    public function test_student_api_fees_include_hostel_fee_demands_in_balance(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('student');
        $program = Program::factory()->create();
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'total_amount' => 10000,
            'scholarship_deduction' => 0,
            'final_amount' => 10000,
            'penalty_amount' => 500,
            'status' => 'pending',
        ]);

        $block = HostelBlock::create([
            'name' => 'API Hostel',
            'gender' => 'mixed',
            'total_floors' => 1,
            'is_active' => true,
        ]);
        $room = HostelRoom::create([
            'hostel_block_id' => $block->id,
            'room_number' => '602',
            'floor' => 1,
            'room_type' => 'single',
            'capacity' => 1,
            'monthly_fee' => 7200,
            'status' => 'available',
        ]);
        $allocation = HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);
        HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => '2026-06',
            'amount' => 7200,
            'status' => 'pending',
            'due_date' => now()->addWeek()->toDateString(),
        ]);
        HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => '2026-05',
            'amount' => 7200,
            'status' => 'paid',
            'due_date' => now()->subMonth()->toDateString(),
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/student/fees')
            ->assertOk()
            ->assertJsonPath('total_due', 24900)
            ->assertJsonPath('balance', 17700)
            ->assertJsonPath('hostel_balance', 7200)
            ->assertJsonCount(2, 'hostel_demands')
            ->assertJsonFragment([
                'month' => '2026-06',
                'amount' => 7200,
                'status' => 'pending',
                'room' => 'API Hostel / Room 602',
            ]);
    }
}
