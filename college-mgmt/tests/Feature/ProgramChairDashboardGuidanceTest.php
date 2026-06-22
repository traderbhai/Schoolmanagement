<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\ApprovalWorkflow;
use App\Models\Batch;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\ProgramSubject;
use App\Models\RoleProgramAssignment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\ClassroomCapacityService;
use App\Services\FacultyWorkloadService;
use App\Services\LoadBalancingService;
use App\Services\SoftConstraintService;
use App\Services\TeacherWorkloadWarningService;
use App\Services\TimetableConflictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramChairDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function chairUser(?Program $program = null): User
    {
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('program_chair');

        if ($program) {
            RoleProgramAssignment::create([
                'user_id' => $user->id,
                'role_name' => 'program_chair',
                'program_id' => $program->id,
                'is_active' => true,
                'assigned_by' => $user->id,
                'assigned_at' => now(),
            ]);
        }

        return $user;
    }

    private function pendingApprovalFor(Program $program): ApprovalWorkflow
    {
        $applicant = Applicant::factory()->create(['program_id' => $program->id]);

        return ApprovalWorkflow::create([
            'approvable_type' => Applicant::class,
            'approvable_id' => $applicant->id,
            'approver_role' => 'program_chair',
            'status' => 'pending',
        ]);
    }

    public function test_dashboard_shows_assignment_needed_when_program_chair_has_no_program_scope(): void
    {
        $user = $this->chairUser();
        Student::factory()->count(2)->create();

        $this->actingAs($user)
            ->get(route('chair.dashboard'))
            ->assertStatus(200)
            ->assertSee('Program Chair Priority')
            ->assertSee('Program assignment needed')
            ->assertSee('No active program is assigned')
            ->assertSee('>0<', false);
    }

    public function test_dashboard_prioritizes_pending_approvals_for_assigned_program_only(): void
    {
        $assignedProgram = Program::factory()->create(['name' => 'Assigned Program']);
        $otherProgram = Program::factory()->create(['name' => 'Other Program']);
        $user = $this->chairUser($assignedProgram);

        $this->pendingApprovalFor($assignedProgram);
        $this->pendingApprovalFor($otherProgram);

        $this->actingAs($user)
            ->get(route('chair.dashboard'))
            ->assertStatus(200)
            ->assertSee('Review 1 pending approval')
            ->assertSee('Open Approvals')
            ->assertSee(route('chair.approvals'), false);

        $this->actingAs($user)
            ->get(route('chair.approvals'))
            ->assertStatus(200)
            ->assertSee('Assigned Program')
            ->assertDontSee('Other Program');
    }

    public function test_program_chair_dashboard_and_at_risk_use_only_published_results(): void
    {
        $program = Program::factory()->create(['name' => 'Published Program']);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'start_date' => now(),
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Official Subject',
        ]);
        $publishedStudent = Student::factory()->create(['program_id' => $program->id]);
        $draftStudent = Student::factory()->create(['program_id' => $program->id]);
        $publishedExam = Exam::factory()->create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'published_at' => now(),
            'name' => 'Published Exam',
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'published_at' => null,
            'name' => 'Draft Exam',
        ]);
        ExamResult::factory()->create([
            'exam_id' => $publishedExam->id,
            'student_id' => $publishedStudent->id,
            'marks_obtained' => 80,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $draftStudent->id,
            'marks_obtained' => 10,
        ]);
        $chair = $this->chairUser($program);

        $dashboard = $this->actingAs($chair)
            ->get(route('chair.dashboard'))
            ->assertOk()
            ->assertSee('Published Exam')
            ->assertDontSee('Draft Exam');

        $this->assertSame(1, $dashboard->viewData('subjectsThisTerm'));
        $this->assertSame(80.0, $dashboard->viewData('avgMarks'));
        $this->assertCount(1, $dashboard->viewData('recentExams'));
        $this->assertSame('Published Exam', $dashboard->viewData('recentExams')->first()->name);
        $this->assertFalse($dashboard->viewData('atRiskStudents')->contains('id', $draftStudent->id));

        $atRisk = $this->actingAs($chair)
            ->get(route('chair.students.at-risk', ['risk' => 'academic']))
            ->assertOk()
            ->assertDontSee($draftStudent->user->name);

        $this->assertFalse($atRisk->viewData('atRisk')->contains('id', $draftStudent->id));
    }

    public function test_program_chair_attendance_views_ignore_draft_timetable_history(): void
    {
        $program = Program::factory()->create(['name' => 'BBA Program Chair Attendance', 'is_active' => true]);
        $course = Course::factory()->create();
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'start_date' => now(),
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Program Chair Attendance Subject',
        ]);
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $chair = $this->chairUser($program);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $chair->id,
        ]);
        $publishedEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $publishedVersion->id,
        ]);
        foreach (range(1, 2) as $day) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $publishedEntry->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => 'present',
                'marked_by' => $teacher->user_id,
            ]);
        }

        $draftEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'draft',
        ]);
        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $draftEntry->id,
            'date' => now()->subDays(3)->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacher->user_id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $chair->id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 3,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $draftVersionEntry->id,
            'date' => now()->subDays(4)->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacher->user_id,
        ]);

        $dashboard = $this->actingAs($chair)
            ->get(route('chair.dashboard'))
            ->assertOk();

        $this->assertSame(100.0, $dashboard->viewData('attendancePct'));
        $this->assertTrue($dashboard->viewData('lowAttSubjects')->isEmpty());
        $this->assertTrue($dashboard->viewData('atRiskStudents')->isEmpty());
        $this->assertSame('No urgent program action today', $dashboard->viewData('chairPriority')['title']);

        $atRisk = $this->actingAs($chair)
            ->get(route('chair.students.at-risk', ['risk' => 'attendance']))
            ->assertOk();

        $this->assertTrue($atRisk->viewData('atRisk')->isEmpty());
        $atRisk->assertDontSee($student->user->name);
    }

    public function test_legacy_timetable_reporting_services_ignore_draft_version_entries(): void
    {
        $program = Program::factory()->create(['name' => 'Legacy Timetable Reports', 'is_active' => true]);
        $course = Course::factory()->create();
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'name' => 'Official Batch']);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'start_date' => now(),
            'end_date' => now()->addWeeks(18),
        ]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'term_number' => 1]);
        $teacher = Teacher::factory()->create();
        $officialRoom = Classroom::factory()->create(['room_number' => 'OFFICIAL-101']);
        $draftRoom = Classroom::factory()->create(['room_number' => 'DRAFT-202']);
        $chair = $this->chairUser($program);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $chair->id,
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $officialRoom->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 1])->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $publishedVersion->id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $chair->id,
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $draftRoom->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '10:00:00', 'sort_order' => 2])->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);

        $workload = app(FacultyWorkloadService::class)->getWorkloadReport($program->id, $term->id);
        $this->assertCount(1, $workload);
        $this->assertSame(1, $workload[0]['session_count']);
        $this->assertEquals(1.0, $workload[0]['total_hours']);

        $loadBalance = app(LoadBalancingService::class)->analyzeLoadBalance($term->id, $program->id);
        $this->assertEquals(1.0, $loadBalance['teachers'][0]['hours']);

        $warning = app(TeacherWorkloadWarningService::class)->getCurrentWorkload($teacher->id, $term->id);
        $this->assertSame(1, $warning['session_count']);
        $this->assertEquals(1.0, $warning['total_hours']);

        $rooms = collect(app(ClassroomCapacityService::class)->getUtilizationReport($program->id, $term->id))->pluck('room_number');
        $this->assertTrue($rooms->contains('OFFICIAL-101'));
        $this->assertFalse($rooms->contains('DRAFT-202'));
    }

    public function test_workload_services_prefer_canonical_pmc_official_sessions_over_legacy_rows(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Canonical Workload Term',
            'start_date' => now(),
            'end_date' => now()->addWeeks(18),
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Canonical Workload Term']);
        $course = Course::factory()->create();
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Canonical Workload Subject',
        ]);
        $teacher = Teacher::factory()->create();
        $legacyOnlyTeacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create([
            'name' => 'Canonical Workload Period',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'sort_order' => 1,
        ]);
        $room = Classroom::factory()->create();
        $actor = User::factory()->create();

        $publishedVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $actor->id,
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $actor->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Canonical Workload Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $publishedVersion->id,
            'created_by' => $actor->id,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Canonical Workload Section A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 40,
            'status' => 'active',
            'is_locked' => true,
        ]);

        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $publishedVersion->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_index' => 1,
            'session_type' => 'lab',
            'duration_slots' => 2,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $actor->id,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $publishedVersion->id,
            'course_group_id' => $group->id,
            'session_index' => 2,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'manual',
            'published_at' => now(),
            'published_by' => $actor->id,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $draftVersion->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_index' => 2,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $legacyOnlyTeacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 3,
            'is_active' => true,
            'status' => 'published',
        ]);

        $workload = app(FacultyWorkloadService::class)->getWorkloadReport($program->id, $term->id);
        $this->assertCount(1, $workload);
        $this->assertSame($teacher->id, $workload[0]['teacher_id']);
        $this->assertSame(2, $workload[0]['session_count']);
        $this->assertEquals(3.0, $workload[0]['total_hours']);
        $this->assertSame('canonical_pmc_official_sessions', $workload[0]['entries'][0]['source']);

        $loadBalance = app(LoadBalancingService::class)->analyzeLoadBalance($term->id, $program->id);
        $this->assertCount(1, $loadBalance['teachers']);
        $this->assertSame($teacher->id, $loadBalance['teachers'][0]['teacher_id']);
        $this->assertEquals(3.0, $loadBalance['teachers'][0]['hours']);
        $this->assertSame('canonical_pmc_official_sessions', $loadBalance['stats']['source']);

        $warning = app(TeacherWorkloadWarningService::class)->getCurrentWorkload($teacher->id, $term->id);
        $this->assertSame(2, $warning['session_count']);
        $this->assertEquals(3.0, $warning['total_hours']);
        $this->assertSame('canonical_pmc_official_sessions', $warning['entries'][0]['source']);

        $legacyOnlyWarning = app(TeacherWorkloadWarningService::class)->getCurrentWorkload($legacyOnlyTeacher->id, $term->id);
        $this->assertSame(0, $legacyOnlyWarning['session_count']);
        $this->assertEquals(0.0, $legacyOnlyWarning['total_hours']);
    }

    public function test_program_chair_dashboard_workload_prefers_canonical_pmc_official_sessions(): void
    {
        $program = Program::factory()->create();
        $user = $this->chairUser($program);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Canonical Dashboard Workload Term',
            'start_date' => now(),
            'end_date' => now()->addWeeks(18),
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Canonical Dashboard Workload Term']);
        $course = Course::factory()->create();
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Canonical Dashboard Workload Subject',
        ]);
        $canonicalTeacherUser = User::factory()->create(['name' => 'Canonical Dashboard Faculty']);
        $legacyTeacherUser = User::factory()->create(['name' => 'Legacy Dashboard Faculty']);
        $canonicalTeacher = Teacher::factory()->create(['user_id' => $canonicalTeacherUser->id]);
        $legacyTeacher = Teacher::factory()->create(['user_id' => $legacyTeacherUser->id]);
        $slot = TimetableSlot::factory()->create([
            'name' => 'Canonical Dashboard Period',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'sort_order' => 1,
        ]);
        $room = Classroom::factory()->create();

        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $user->id,
            'published_by' => $user->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Canonical Dashboard Workload Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $user->id,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Canonical Dashboard Section A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 40,
            'status' => 'active',
            'is_locked' => true,
        ]);

        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 2,
            'teacher_id' => $canonicalTeacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $user->id,
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $legacyTeacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('chair.dashboard'))
            ->assertOk()
            ->assertSee('Faculty Workload')
            ->assertSee('Canonical Dashboard Faculty')
            ->assertDontSee('Legacy Dashboard Faculty');

        $this->actingAs($user)
            ->get(route('chair.faculty.workload', ['term_id' => $term->id]))
            ->assertOk()
            ->assertSee('Canonical Dashboard Faculty')
            ->assertDontSee('Legacy Dashboard Faculty');
    }

    public function test_program_chair_analytics_counts_official_canonical_sessions_before_legacy_entries(): void
    {
        $program = Program::factory()->create();
        $user = $this->chairUser($program);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Canonical Analytics Term',
            'start_date' => now(),
            'end_date' => now()->addWeeks(18),
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Canonical Analytics Term']);
        $course = Course::factory()->create();
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Canonical Analytics Subject',
        ]);
        $teacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create([
            'name' => 'Canonical Analytics Period',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'sort_order' => 1,
        ]);
        $room = Classroom::factory()->create();
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $user->id,
            'published_by' => $user->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Canonical Analytics Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $user->id,
            'status' => 'published',
            'scheduled_count' => 2,
            'quality_score' => 100,
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Canonical Analytics Section A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 40,
            'status' => 'active',
            'is_locked' => true,
        ]);

        foreach ([1, 2] as $day) {
            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'timetable_version_id' => $version->id,
                'course_group_id' => $group->id,
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'session_index' => $day,
                'session_type' => 'lecture',
                'duration_slots' => 1,
                'teacher_id' => $teacher->id,
                'classroom_id' => $room->id,
                'day_of_week' => $day,
                'timetable_slot_id' => $slot->id,
                'status' => 'scheduled',
                'official_status' => 'published',
                'source_type' => 'generated',
                'published_at' => now(),
                'published_by' => $user->id,
            ]);
        }

        foreach (range(1, 5) as $index) {
            TimetableEntry::factory()->create([
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $program->id,
                'term_id' => $term->id,
                'batch_id' => $batch->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'classroom_id' => $room->id,
                'timetable_slot_id' => TimetableSlot::factory()->create(['sort_order' => $index + 10])->id,
                'day_of_week' => $index,
                'is_active' => true,
                'status' => 'published',
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('chair.analytics', ['program_id' => $program->id, 'term_id' => $term->id]))
            ->assertOk()
            ->assertSee('Timetable Analytics Dashboard');

        $this->assertSame(2, $response->viewData('dashboardData')['totalEntries']);
    }

    public function test_program_chair_read_only_timetable_shows_parallel_official_pmc_sessions(): void
    {
        $program = Program::factory()->create();
        $user = $this->chairUser($program);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'name' => 'Batch 2026']);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Canonical Timetable Term',
            'start_date' => now(),
            'end_date' => now()->addWeeks(18),
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Canonical Timetable Term']);
        $course = Course::factory()->create();
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Parallel Official Subject',
            'code' => 'POS101',
        ]);
        $legacySubject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Stale Legacy Timetable Subject',
        ]);
        $slot = TimetableSlot::factory()->create([
            'name' => 'Parallel Period',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'sort_order' => 1,
        ]);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $user->id,
            'published_by' => $user->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Program Chair Read Only Timetable Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $user->id,
            'status' => 'published',
            'scheduled_count' => 2,
            'quality_score' => 100,
        ]);

        foreach (['Section A', 'Section B'] as $index => $name) {
            $group = AcademicPmcCourseGroup::create([
                'name' => $name,
                'group_type' => 'core_section',
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'min_capacity' => 1,
                'max_capacity' => 60,
                'current_strength' => 40,
                'status' => 'active',
                'is_locked' => true,
            ]);
            $teacherUser = User::factory()->create(['name' => $name . ' Faculty']);
            $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
            $room = Classroom::factory()->create(['name' => $name . ' Room']);

            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'timetable_version_id' => $version->id,
                'course_group_id' => $group->id,
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'session_index' => $index + 1,
                'session_type' => 'lecture',
                'duration_slots' => 1,
                'teacher_id' => $teacher->id,
                'classroom_id' => $room->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $slot->id,
                'status' => 'scheduled',
                'official_status' => 'published',
                'source_type' => 'generated',
                'published_at' => now(),
                'published_by' => $user->id,
            ]);
        }

        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'subject_id' => $legacySubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create(['name' => 'Legacy Room'])->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['sort_order' => 2])->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('chair.timetable'))
            ->assertOk()
            ->assertSee('Program Timetable')
            ->assertSee('Official PMC')
            ->assertSee('Parallel Official Subject')
            ->assertSee('Section A')
            ->assertSee('Section B')
            ->assertSee('Section A Faculty')
            ->assertSee('Section B Faculty')
            ->assertSee('Section A Room')
            ->assertSee('Section B Room')
            ->assertDontSee('Stale Legacy Timetable Subject')
            ->assertDontSee('Legacy Room');
    }

    public function test_room_utilization_prefers_canonical_pmc_official_sessions_over_legacy_rows(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Canonical Room Term',
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Canonical Room Term']);
        $course = Course::factory()->create();
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Canonical Room Subject',
        ]);
        $teacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create(['sort_order' => 1]);
        $canonicalRoom = Classroom::factory()->create([
            'room_number' => 'PMC-CAN-ROOM',
            'name' => 'PMC Canonical Room',
            'capacity' => 20,
        ]);
        $legacyRoom = Classroom::factory()->create([
            'room_number' => 'LEGACY-ROOM',
            'name' => 'Legacy Flattened Room',
            'capacity' => 80,
        ]);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create()->id,
            'published_by' => User::factory()->create()->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Canonical Room Utilization Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $version->created_by,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Canonical Room Section A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 35,
            'status' => 'active',
            'is_locked' => true,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $canonicalRoom->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $version->published_by,
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $legacyRoom->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['sort_order' => 2])->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'published',
        ]);

        $report = collect(app(ClassroomCapacityService::class)->getUtilizationReport($program->id, $term->id));
        $violations = app(ClassroomCapacityService::class)->findCapacityViolations($program->id, $term->id);

        $this->assertSame(['PMC-CAN-ROOM'], $report->pluck('room_number')->all());
        $this->assertSame('canonical_pmc_official_sessions', $report->first()['source']);
        $this->assertSame(35, $report->first()['max_batch_size']);
        $this->assertSame(1, $report->first()['session_count']);
        $this->assertSame('over-capacity', $report->first()['status']);
        $this->assertSame('PMC-CAN-ROOM', $violations[0]['room_number']);
        $this->assertSame('Canonical Room Section A', $violations[0]['batch_name']);
    }

    public function test_program_chair_cannot_approve_another_program_approval(): void
    {
        $assignedProgram = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $user = $this->chairUser($assignedProgram);
        $foreignApproval = $this->pendingApprovalFor($otherProgram);

        $this->actingAs($user)
            ->post(route('chair.approve', $foreignApproval), ['remarks' => 'Looks fine'])
            ->assertForbidden();
    }

    public function test_program_chair_cannot_reapprove_or_reject_finalized_approval(): void
    {
        $program = Program::factory()->create();
        $user = $this->chairUser($program);
        $approval = $this->pendingApprovalFor($program);

        $this->actingAs($user)
            ->post(route('chair.approve', $approval), ['remarks' => 'Approved once'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('chair.reject', $approval->fresh()), ['rejection_reason' => 'Trying to reverse'])
            ->assertForbidden();

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $approval->id,
            'status' => 'approved',
            'remarks' => 'Approved once',
        ]);
    }

    public function test_program_chair_cannot_action_hod_queue_even_for_same_program(): void
    {
        $program = Program::factory()->create();
        $user = $this->chairUser($program);
        $applicant = Applicant::factory()->create(['program_id' => $program->id]);
        $approval = ApprovalWorkflow::create([
            'approvable_type' => Applicant::class,
            'approvable_id' => $applicant->id,
            'approver_role' => 'hod',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('chair.approve', $approval), ['remarks' => 'Wrong queue'])
            ->assertForbidden();

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $approval->id,
            'status' => 'pending',
        ]);
    }

    public function test_program_chair_can_override_assigned_program_elective_with_audit_reason(): void
    {
        $program = Program::factory()->create();
        $term = Term::factory()->create(['program_id' => $program->id, 'term_number' => 1, 'is_current' => true]);
        $oldSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Old Elective']);
        $newSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'New Elective']);
        ProgramSubject::create([
            'program_id' => $program->id,
            'subject_id' => $oldSubject->id,
            'term_id' => $term->id,
            'type' => 'elective',
            'credits' => 3,
            'is_active' => true,
        ]);
        ProgramSubject::create([
            'program_id' => $program->id,
            'subject_id' => $newSubject->id,
            'term_id' => $term->id,
            'type' => 'elective',
            'credits' => 3,
            'is_active' => true,
        ]);
        $student = Student::factory()->create(['program_id' => $program->id, 'current_term_id' => $term->id]);
        $enrollment = StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $oldSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);
        $chair = $this->chairUser($program);

        $this->actingAs($chair)
            ->from(route('chair.students.elective-override'))
            ->post(route('chair.students.elective-override.change', $enrollment), [
                'new_subject_id' => $newSubject->id,
                'reason' => 'Student shifted elective after counselling.',
            ])
            ->assertRedirect(route('chair.students.elective-override'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('student_subject_enrollments', [
            'id' => $enrollment->id,
            'subject_id' => $newSubject->id,
            'previous_subject_id' => $oldSubject->id,
            'override_reason' => 'Student shifted elective after counselling.',
            'overridden_by' => $chair->id,
        ]);
    }

    public function test_program_chair_mentor_assignment_stores_teacher_user_identity(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $chair = $this->chairUser($program);
        $mentor = Teacher::factory()->create(['status' => 'active']);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $batchStudent = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);

        $this->actingAs($chair)
            ->get(route('chair.students.mentors'))
            ->assertOk();

        $this->actingAs($chair)
            ->post(route('chair.students.mentors.assign'), [
                'student_id' => $student->id,
                'mentor_id' => $mentor->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'mentor_id' => $mentor->user_id,
        ]);

        $this->actingAs($chair)
            ->post(route('chair.students.mentors.bulk'), [
                'batch_id' => $batch->id,
                'mentor_id' => $mentor->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('students', [
            'id' => $batchStudent->id,
            'mentor_id' => $mentor->user_id,
        ]);
    }

    public function test_program_chair_elective_override_blocks_out_of_scope_invalid_and_duplicate_changes(): void
    {
        $assignedProgram = Program::factory()->create();
        $foreignProgram = Program::factory()->create();
        $term = Term::factory()->create(['program_id' => $assignedProgram->id, 'term_number' => 1, 'is_current' => true]);
        $assignedSubject = Subject::factory()->create(['program_id' => $assignedProgram->id]);
        $replacementSubject = Subject::factory()->create(['program_id' => $assignedProgram->id]);
        $duplicateSubject = Subject::factory()->create(['program_id' => $assignedProgram->id]);
        $foreignSubject = Subject::factory()->create(['program_id' => $foreignProgram->id]);

        foreach ([$assignedSubject, $replacementSubject, $duplicateSubject] as $subject) {
            ProgramSubject::create([
                'program_id' => $assignedProgram->id,
                'subject_id' => $subject->id,
                'term_id' => $term->id,
                'type' => 'elective',
                'credits' => 3,
                'is_active' => true,
            ]);
        }

        $student = Student::factory()->create(['program_id' => $assignedProgram->id, 'current_term_id' => $term->id]);
        $enrollment = StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $assignedSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $duplicateSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);

        $foreignStudent = Student::factory()->create(['program_id' => $foreignProgram->id]);
        $foreignEnrollment = StudentSubjectEnrollment::create([
            'student_id' => $foreignStudent->id,
            'subject_id' => $foreignSubject->id,
            'term_id' => null,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);
        $chair = $this->chairUser($assignedProgram);

        $this->actingAs($chair)
            ->post(route('chair.students.elective-override.change', $foreignEnrollment), [
                'new_subject_id' => $replacementSubject->id,
                'reason' => 'Out of scope.',
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->from(route('chair.students.elective-override'))
            ->post(route('chair.students.elective-override.change', $enrollment), [
                'new_subject_id' => $foreignSubject->id,
                'reason' => 'Wrong program.',
            ])
            ->assertRedirect(route('chair.students.elective-override'))
            ->assertSessionHasErrors('new_subject_id');

        $this->actingAs($chair)
            ->from(route('chair.students.elective-override'))
            ->post(route('chair.students.elective-override.change', $enrollment), [
                'new_subject_id' => $duplicateSubject->id,
                'reason' => 'Duplicate active subject.',
            ])
            ->assertRedirect(route('chair.students.elective-override'))
            ->assertSessionHasErrors('new_subject_id');

        $this->assertDatabaseHas('student_subject_enrollments', [
            'id' => $enrollment->id,
            'subject_id' => $assignedSubject->id,
        ]);
    }

    public function test_soft_constraint_audit_flags_unavailable_teacher_assignments_from_real_availability_field(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
        ]);
        $semester = Semester::factory()->create(['number' => 1]);
        $course = Course::factory()->create();
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Unavailable Faculty Planning Subject',
        ]);
        $teacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create(['name' => 'Unavailable Slot', 'sort_order' => 1]);

        TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'term_id' => $term->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'availability' => 'unavailable',
        ]);

        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'draft',
        ]);

        $audit = app(SoftConstraintService::class)->auditTermConstraints($term->id, $program->id, $batch->id);

        $this->assertGreaterThan(0, $audit['total_issues']);
        $this->assertContains('unavailable_time_assigned', collect($audit['issues'])->pluck('type')->all());
    }

    public function test_timetable_conflict_capacity_uses_actual_batch_students_with_intake_fallback(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create([
            'program_id' => $program->id,
            'intake_capacity' => 30,
            'name' => 'Capacity Batch',
        ]);
        Student::factory()->count(3)->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
        ]);
        $smallRoom = Classroom::factory()->create([
            'room_number' => 'CAP-2',
            'capacity' => 2,
        ]);
        $emptyBatch = Batch::factory()->create([
            'program_id' => $program->id,
            'intake_capacity' => 5,
            'name' => 'Fallback Batch',
        ]);
        $tinyRoom = Classroom::factory()->create([
            'room_number' => 'CAP-4',
            'capacity' => 4,
        ]);

        $service = app(TimetableConflictService::class);

        $studentBasedConflicts = $service->checkCapacity($smallRoom->id, $batch->id);
        $fallbackConflicts = $service->checkCapacity($tinyRoom->id, $emptyBatch->id);

        $this->assertNotEmpty($studentBasedConflicts);
        $this->assertStringContainsString('3 students', $studentBasedConflicts[0]);
        $this->assertNotEmpty($fallbackConflicts);
        $this->assertStringContainsString('5 students', $fallbackConflicts[0]);
    }
}
