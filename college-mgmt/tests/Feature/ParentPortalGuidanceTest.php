<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\FeeDemand;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\HostelAllocation;
use App\Models\HostelBlock;
use App\Models\HostelFeeDemand;
use App\Models\HostelRoom;
use App\Models\Notice;
use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParentPortalGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function parentWithStudent(): array
    {
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('parent');
        $student = Student::factory()->create();
        $parent = ParentProfile::create([
            'user_id' => $user->id,
            'relation' => 'guardian',
            'phone' => '9999999999',
        ]);
        $parent->students()->attach($student);

        return [$user, $student];
    }

    public function test_parent_dashboard_uses_fee_demands_for_priority_and_balance(): void
    {
        [$user, $student] = $this->parentWithStudent();
        $term = Term::factory()->create(['program_id' => $student->program_id]);

        FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'fee_type' => 'Legacy structure',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 50000,
            'scholarship_deduction' => 0,
            'final_amount' => 50000,
            'penalty_amount' => 5000,
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('parent.dashboard'))
            ->assertStatus(200)
            ->assertSee('Parent Priority')
            ->assertSee('Fee demand is overdue')
            ->assertSee('Rs. 55,000')
            ->assertSee('Open demands: 1')
            ->assertDontSee('999,999');
    }

    public function test_parent_fee_page_shows_demand_breakdown_and_overdue_guidance(): void
    {
        [$user, $student] = $this->parentWithStudent();
        $term = Term::factory()->create(['program_id' => $student->program_id, 'name' => 'Term 1']);

        FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'fee_type' => 'Legacy structure',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 45000,
            'scholarship_deduction' => 5000,
            'final_amount' => 40000,
            'penalty_amount' => 1000,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'overdue',
        ]);

        $this->actingAs($user)
            ->get(route('parent.children.fees', $student))
            ->assertStatus(200)
            ->assertSee('Fee follow-up needed')
            ->assertSee('Term 1')
            ->assertSee('Rs. 41,000')
            ->assertSee('Overdue')
            ->assertDontSee('999,999');
    }

    public function test_parent_fee_page_shows_only_paid_receipt_history(): void
    {
        [$user, $student] = $this->parentWithStudent();
        $structure = FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'fee_type' => 'Tuition',
            'amount' => 20000,
        ]);

        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'amount_paid' => 5000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'PARENT-PAID-001',
            'payment_method' => 'online',
            'status' => 'paid',
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'amount_paid' => 7000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'PARENT-PENDING-001',
            'payment_method' => 'online',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('parent.children.fees', $student))
            ->assertOk()
            ->assertSee('PARENT-PAID-001')
            ->assertDontSee('PARENT-PENDING-001');
    }

    public function test_parent_dashboard_and_fee_page_include_hostel_fee_dues(): void
    {
        [$user, $student] = $this->parentWithStudent();
        $block = HostelBlock::create([
            'name' => 'Parent Visible Hostel',
            'gender' => 'mixed',
            'total_floors' => 2,
            'is_active' => true,
        ]);
        $room = HostelRoom::create([
            'hostel_block_id' => $block->id,
            'room_number' => '502',
            'floor' => 2,
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
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee('Fee demand is overdue')
            ->assertSee('Rs. 7,200')
            ->assertSee('Open demands: 1');

        $this->actingAs($user)
            ->get(route('parent.children.fees', $student))
            ->assertOk()
            ->assertSee('Fee follow-up needed')
            ->assertSee('Hostel Fee Demands')
            ->assertSee('Parent Visible Hostel')
            ->assertSee('Room 502')
            ->assertSee('Rs. 7,200')
            ->assertSee('Overdue');
    }

    public function test_parent_attendance_and_results_use_canonical_enrolled_subjects_only(): void
    {
        [$user, $student, $semester, $enrolledSubject, $unenrolledSubject] = $this->parentAcademicFixture();

        $this->actingAs($user)
            ->get(route('parent.children.attendance', ['student' => $student, 'semester_id' => $semester->id]))
            ->assertOk()
            ->assertSee('Parent Enrolled Subject')
            ->assertSee('100%')
            ->assertDontSee('Parent Unenrolled Subject');

        $this->actingAs($user)
            ->get(route('parent.children.results', ['student' => $student, 'semester_id' => $semester->id]))
            ->assertOk()
            ->assertSee('Parent Enrolled Subject')
            ->assertSee('88')
            ->assertDontSee('Parent Unenrolled Subject');
    }

    public function test_parent_results_hide_unpublished_draft_marks(): void
    {
        [$user, $student, $semester, $enrolledSubject] = $this->parentAcademicFixture();

        $draftExam = Exam::factory()->create([
            'program_id' => $student->program_id,
            'term_id' => $student->current_term_id,
            'semester_id' => $semester->id,
            'subject_id' => $enrolledSubject->id,
            'name' => 'Parent Draft Result Exam',
            'total_marks' => 100,
            'published_at' => null,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 99,
            'is_absent' => false,
        ]);

        $this->actingAs($user)
            ->get(route('parent.children.results', ['student' => $student, 'semester_id' => $semester->id]))
            ->assertOk()
            ->assertSee('Parent Enrolled Subject')
            ->assertSee('88')
            ->assertDontSee('Parent Draft Result Exam')
            ->assertDontSee('99.00');
    }

    public function test_parent_cannot_open_unlinked_child_detail_routes_by_direct_url(): void
    {
        [$user] = $this->parentWithStudent();
        $unlinkedStudent = Student::factory()->create();

        foreach ([
            route('parent.children.attendance', $unlinkedStudent),
            route('parent.children.results', $unlinkedStudent),
            route('parent.children.fees', $unlinkedStudent),
        ] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertForbidden();
        }
    }

    public function test_parent_attendance_excludes_draft_timetable_rows_and_draft_versions(): void
    {
        [$user, $student, $semester, $enrolledSubject] = $this->parentAcademicFixture();
        $draftSubject = Subject::factory()->create([
            'program_id' => $student->program_id,
            'term_number' => 1,
            'name' => 'Parent Draft Timetable Subject',
        ]);
        $draftVersionSubject = Subject::factory()->create([
            'program_id' => $student->program_id,
            'term_number' => 1,
            'name' => 'Parent Draft Version Subject',
        ]);
        foreach ([$draftSubject, $draftVersionSubject] as $subject) {
            StudentSubjectEnrollment::create([
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'term_id' => $student->current_term_id,
                'enrollment_type' => 'compulsory',
                'status' => 'active',
            ]);
        }
        $draftVersion = TimetableVersion::create([
            'program_id' => $student->program_id,
            'term_id' => $student->current_term_id,
            'batch_id' => $student->batch_id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);

        foreach ([
            [$draftSubject, 'draft', null],
            [$draftVersionSubject, 'published', $draftVersion->id],
        ] as [$subject, $status, $versionId]) {
            $entry = TimetableEntry::factory()->create([
                'program_id' => $student->program_id,
                'batch_id' => $student->batch_id,
                'course_id' => $student->course_id,
                'term_id' => $student->current_term_id,
                'semester_id' => $semester->id,
                'subject_id' => $subject->id,
                'teacher_id' => Teacher::factory()->create()->id,
                'classroom_id' => Classroom::factory()->create()->id,
                'timetable_slot_id' => TimetableSlot::factory()->create()->id,
                'day_of_week' => now()->dayOfWeekIso,
                'is_active' => true,
                'status' => $status,
                'timetable_version_id' => $versionId,
            ]);
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $entry->id,
                'date' => now()->toDateString(),
                'status' => 'absent',
            ]);
        }

        $this->actingAs($user)
            ->get(route('parent.children.attendance', ['student' => $student, 'semester_id' => $semester->id]))
            ->assertOk()
            ->assertSee('Parent Enrolled Subject')
            ->assertSee('100%')
            ->assertDontSee('Parent Draft Timetable Subject')
            ->assertDontSee('Parent Draft Version Subject');

        $this->actingAs($user)
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertDontSee('Attendance needs attention');
    }

    public function test_parent_dashboard_notice_preview_respects_audience_and_active_dates(): void
    {
        [$user] = $this->parentWithStudent();
        $admin = User::factory()->create();

        Notice::create([
            'user_id' => $admin->id,
            'title' => 'Parent Visible Family Notice',
            'content' => 'Visible to parents through student audience.',
            'audience' => 'students',
            'publish_date' => now()->subDay()->toDateString(),
            'is_published' => true,
        ]);
        Notice::create([
            'user_id' => $admin->id,
            'title' => 'Parent Visible All Notice',
            'content' => 'Visible to all audiences.',
            'audience' => 'all',
            'publish_date' => now()->subDay()->toDateString(),
            'is_published' => true,
        ]);
        Notice::create([
            'user_id' => $admin->id,
            'title' => 'Teacher Only Dashboard Leak',
            'content' => 'Should stay hidden from parents.',
            'audience' => 'teachers',
            'publish_date' => now()->subDay()->toDateString(),
            'is_published' => true,
        ]);
        Notice::create([
            'user_id' => $admin->id,
            'title' => 'Admin Only Dashboard Leak',
            'content' => 'Should stay hidden from parents.',
            'audience' => 'admin',
            'publish_date' => now()->subDay()->toDateString(),
            'is_published' => true,
        ]);
        Notice::create([
            'user_id' => $admin->id,
            'title' => 'Future Family Notice',
            'content' => 'Should not be visible yet.',
            'audience' => 'students',
            'publish_date' => now()->addDay()->toDateString(),
            'is_published' => true,
        ]);
        Notice::create([
            'user_id' => $admin->id,
            'title' => 'Expired Family Notice',
            'content' => 'Should no longer be visible.',
            'audience' => 'students',
            'publish_date' => now()->subDays(10)->toDateString(),
            'expiry_date' => now()->subDay()->toDateString(),
            'is_published' => true,
        ]);

        $this->actingAs($user)
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee('Parent Visible Family Notice')
            ->assertSee('Parent Visible All Notice')
            ->assertDontSee('Teacher Only Dashboard Leak')
            ->assertDontSee('Admin Only Dashboard Leak')
            ->assertDontSee('Future Family Notice')
            ->assertDontSee('Expired Family Notice');
    }

    private function parentAcademicFixture(): array
    {
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('parent');
        $program = Program::factory()->create(['is_active' => true]);
        $course = Course::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $parent = ParentProfile::create([
            'user_id' => $user->id,
            'relation' => 'guardian',
            'phone' => '9999999999',
        ]);
        $parent->students()->attach($student);

        $enrolledSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Parent Enrolled Subject',
        ]);
        $unenrolledSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Parent Unenrolled Subject',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $enrolledSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        foreach ([$enrolledSubject, $unenrolledSubject] as $subject) {
            $entry = TimetableEntry::factory()->create([
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'course_id' => $course->id,
                'term_id' => $term->id,
                'semester_id' => $semester->id,
                'subject_id' => $subject->id,
                'teacher_id' => Teacher::factory()->create()->id,
                'classroom_id' => Classroom::factory()->create()->id,
                'timetable_slot_id' => TimetableSlot::factory()->create()->id,
                'day_of_week' => now()->dayOfWeekIso,
                'is_active' => true,
            ]);
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $entry->id,
                'date' => now()->toDateString(),
                'status' => 'present',
            ]);
            $exam = Exam::factory()->create([
                'program_id' => $program->id,
                'term_id' => $term->id,
                'semester_id' => $semester->id,
                'subject_id' => $subject->id,
                'name' => $subject->name . ' Exam',
                'total_marks' => 100,
                'published_at' => now(),
            ]);
            ExamResult::factory()->create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'marks_obtained' => $subject->id === $enrolledSubject->id ? 88 : 66,
                'is_absent' => false,
            ]);
        }

        return [$user, $student, $semester, $enrolledSubject, $unenrolledSubject];
    }
}
