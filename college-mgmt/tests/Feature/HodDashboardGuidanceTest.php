<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApprovalWorkflow;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Models\LeaveApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HodDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function hodUser(?Department $department = null): User
    {
        Role::firstOrCreate(['name' => 'hod', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('hod');

        if ($department) {
            Teacher::factory()->create([
                'user_id' => $user->id,
                'department_id' => $department->id,
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
            'approver_role' => 'hod',
            'status' => 'pending',
        ]);
    }

    public function test_hod_dashboard_requires_department_profile_for_hod_users(): void
    {
        $user = $this->hodUser();
        Student::factory()->count(2)->create();

        $this->actingAs($user)
            ->get(route('hod.dashboard'))
            ->assertStatus(200)
            ->assertSee('HOD Priority')
            ->assertSee('Department profile needed')
            ->assertSee('Your HOD account is not linked to a teacher department profile')
            ->assertSee('>0<', false);
    }

    public function test_hod_dashboard_and_approvals_are_scoped_to_department(): void
    {
        $department = Department::factory()->create(['name' => 'Computer Science']);
        $otherDepartment = Department::factory()->create(['name' => 'Mechanical']);
        $program = Program::factory()->create(['department_id' => $department->id, 'name' => 'BCA']);
        $otherProgram = Program::factory()->create(['department_id' => $otherDepartment->id, 'name' => 'BME']);
        $user = $this->hodUser($department);

        $this->pendingApprovalFor($program);
        $this->pendingApprovalFor($otherProgram);

        $this->actingAs($user)
            ->get(route('hod.dashboard'))
            ->assertStatus(200)
            ->assertSee('Review 1 department approval')
            ->assertSee('Open Approvals')
            ->assertSee(route('hod.approvals'), false);

        $this->actingAs($user)
            ->get(route('hod.approvals'))
            ->assertStatus(200)
            ->assertSee('BCA')
            ->assertDontSee('BME');
    }

    public function test_hod_dashboard_and_department_performance_use_only_published_results(): void
    {
        $department = Department::factory()->create(['name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id]);
        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'name' => 'Department Analytics',
        ]);
        $publishedStudent = Student::factory()->create(['program_id' => $program->id]);
        $draftStudent = Student::factory()->create(['program_id' => $program->id]);
        $publishedExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'published_at' => now(),
            'passing_marks' => 40,
            'name' => 'Published HOD Exam',
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'published_at' => null,
            'passing_marks' => 40,
            'name' => 'Draft HOD Exam',
        ]);
        ExamResult::factory()->create([
            'exam_id' => $publishedExam->id,
            'student_id' => $publishedStudent->id,
            'marks_obtained' => 90,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $draftStudent->id,
            'marks_obtained' => 20,
        ]);
        $hod = $this->hodUser($department);

        $dashboard = $this->actingAs($hod)
            ->get(route('hod.dashboard'))
            ->assertOk()
            ->assertSee('Published HOD Exam')
            ->assertDontSee('Draft HOD Exam');

        $this->assertCount(1, $dashboard->viewData('recentExams'));
        $this->assertSame(90.0, $dashboard->viewData('recentExams')->first()->avg_marks);

        $performance = $this->actingAs($hod)
            ->get(route('hod.department-performance'))
            ->assertOk();

        $subjectStats = $performance->viewData('subjects')->firstWhere('name', 'Department Analytics');
        $this->assertSame(1, $subjectStats->exam_count);
        $this->assertSame(1, $subjectStats->result_count);
        $this->assertSame(90.0, $subjectStats->avg_marks);
        $this->assertSame(100.0, $subjectStats->pass_rate);
    }

    public function test_hod_attendance_kpis_ignore_draft_timetable_history(): void
    {
        $department = Department::factory()->create(['name' => 'Attendance Department']);
        $program = Program::factory()->create(['department_id' => $department->id]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Official Attendance Subject',
        ]);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $faculty = Teacher::factory()->create(['department_id' => $department->id]);
        $hod = $this->hodUser($department);

        $publishedEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $faculty->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
        ]);
        foreach (range(1, 2) as $day) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $publishedEntry->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => 'present',
                'marked_by' => $faculty->user_id,
            ]);
        }

        $draftEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $faculty->id,
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
            'marked_by' => $faculty->user_id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $hod->id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $faculty->id,
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
            'marked_by' => $faculty->user_id,
        ]);

        $dashboard = $this->actingAs($hod)
            ->get(route('hod.dashboard'))
            ->assertOk();
        $this->assertSame(100.0, $dashboard->viewData('attendancePct'));
        $this->assertSame('No urgent HOD action today', $dashboard->viewData('hodPriority')['title']);

        $performance = $this->actingAs($hod)
            ->get(route('hod.department-performance'))
            ->assertOk();
        $subjectStats = $performance->viewData('subjects')->firstWhere('name', 'Official Attendance Subject');
        $this->assertSame(100.0, $subjectStats->attendance_pct);
    }

    public function test_hod_cannot_approve_another_department_approval(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $program = Program::factory()->create(['department_id' => $department->id]);
        $otherProgram = Program::factory()->create(['department_id' => $otherDepartment->id]);
        $user = $this->hodUser($department);
        $foreignApproval = $this->pendingApprovalFor($otherProgram);

        $this->pendingApprovalFor($program);

        $this->actingAs($user)
            ->post(route('hod.approve', $foreignApproval), ['remarks' => 'Approved'])
            ->assertForbidden();
    }

    public function test_hod_cannot_reapprove_or_reject_finalized_approval(): void
    {
        $department = Department::factory()->create();
        $program = Program::factory()->create(['department_id' => $department->id]);
        $user = $this->hodUser($department);
        $approval = $this->pendingApprovalFor($program);

        $this->actingAs($user)
            ->post(route('hod.approve', $approval), ['remarks' => 'Approved once'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('hod.reject', $approval->fresh()), ['rejection_reason' => 'Trying to reverse'])
            ->assertForbidden();

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $approval->id,
            'status' => 'approved',
            'remarks' => 'Approved once',
        ]);
    }

    public function test_hod_cannot_action_program_chair_queue_even_inside_department(): void
    {
        $department = Department::factory()->create();
        $program = Program::factory()->create(['department_id' => $department->id]);
        $user = $this->hodUser($department);
        $applicant = Applicant::factory()->create(['program_id' => $program->id]);
        $approval = ApprovalWorkflow::create([
            'approvable_type' => Applicant::class,
            'approvable_id' => $applicant->id,
            'approver_role' => 'program_chair',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('hod.approve', $approval), ['remarks' => 'Wrong queue'])
            ->assertForbidden();

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $approval->id,
            'status' => 'pending',
        ]);
    }

    public function test_hod_leave_review_modal_uses_named_route_action(): void
    {
        $department = Department::factory()->create(['name' => 'Computer Science']);
        $program = Program::factory()->create(['department_id' => $department->id]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        $faculty = Teacher::factory()->create(['department_id' => $department->id]);
        $leave = LeaveApplication::create([
            'student_id' => $student->id,
            'teacher_id' => $faculty->id,
            'leave_type' => 'medical',
            'from_date' => now()->addDay()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'days' => 2,
            'reason' => 'Medical consultation.',
            'status' => 'pending',
        ]);
        $user = $this->hodUser($department);

        $this->actingAs($user)
            ->get(route('hod.leaves'))
            ->assertStatus(200)
            ->assertSee(route('hod.leaves.review', $leave), false)
            ->assertSee('data-review-action="approved"', false)
            ->assertSee('data-review-action="rejected"', false)
            ->assertSee('onclick="openReview(this)"', false);
    }
}
