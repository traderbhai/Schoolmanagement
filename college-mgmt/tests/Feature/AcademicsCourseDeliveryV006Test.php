<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Attendance;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectFacultyAssignment;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Services\AcademicCourseDeliveryService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsCourseDeliveryV006Test extends TestCase
{
    use RefreshDatabase;

    private function seedCourseFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Riya Sharma']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return compact('department', 'program', 'subject', 'student');
    }

    public function test_faculty_can_open_course_delivery_dashboard(): void
    {
        $this->seedCourseFixture();
        $faculty = User::where('email', 'pmc.faculty@college.com')->firstOrFail();

        $this->actingAs($faculty)
            ->get(route('academics.course-delivery.index'))
            ->assertOk()
            ->assertSee('Course Delivery OS')
            ->assertSee('Course Load')
            ->assertSee('Session Delivery')
            ->assertSee('Attendance Interventions')
            ->assertSee('Course Engagement')
            ->assertSee('Mentor Actions');
    }

    public function test_course_delivery_source_lists_are_database_backed(): void
    {
        $fixture = $this->seedCourseFixture();
        $faculty = User::where('email', 'pmc.faculty@college.com')->firstOrFail();

        $this->actingAs($faculty)
            ->get(route('academics.course-delivery.course-engagement'))
            ->assertOk()
            ->assertSee('Course Engagement')
            ->assertSee('Filtered Source List')
            ->assertSee('Course Delivery Lab Prep')
            ->assertSee('Clarify attendance recovery task');

        $this->actingAs($faculty)
            ->get(route('academics.course-delivery.attendance-interventions'))
            ->assertOk()
            ->assertSee('Attendance Interventions')
            ->assertSee('Riya Sharma')
            ->assertDontSee('Student #'.$fixture['student']->id);
    }

    public function test_course_delivery_service_respects_faculty_assignment_scope(): void
    {
        $this->seedCourseFixture();
        $faculty = User::where('email', 'pmc.faculty@college.com')->firstOrFail();
        $otherUser = User::factory()->create(['name' => 'Other Faculty']);
        $otherTeacher = Teacher::factory()->create(['user_id' => $otherUser->id]);
        $hiddenSubject = Subject::factory()->create(['name' => 'Hidden Delivery Subject', 'is_active' => true]);
        SubjectFacultyAssignment::create([
            'subject_id' => $hiddenSubject->id,
            'teacher_id' => $otherTeacher->id,
            'term_id' => \App\Models\Term::query()->value('id'),
            'program_id' => $hiddenSubject->program_id,
            'assigned_by' => $faculty->id,
            'is_primary' => true,
        ]);

        $items = app(AcademicCourseDeliveryService::class)->courseLoad($faculty)['items'];
        $titles = collect($items)->pluck('title');

        $this->assertFalse($titles->contains('Hidden Delivery Subject'));
    }

    public function test_teacher_attendance_interventions_are_limited_to_assigned_subject_roster(): void
    {
        $fixture = $this->seedCourseFixture();
        $teacherUser = User::factory()->create(['name' => 'Scoped Delivery Teacher']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $teacherUser->assignRole('teacher');
        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'department_id' => $fixture['department']->id,
        ]);
        $term = \App\Models\Term::factory()->create(['program_id' => $fixture['program']->id]);
        $course = Course::factory()->create(['department_id' => $fixture['department']->id]);
        $semester = Semester::where('is_current', true)->firstOrFail();

        SubjectFacultyAssignment::create([
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'term_id' => $term->id,
            'program_id' => $fixture['program']->id,
            'assigned_by' => $teacherUser->id,
            'is_primary' => true,
        ]);

        $enrolledStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Assigned Subject Attendance Risk'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);
        $sameProgramUnenrolledStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Same Program Unassigned Attendance Risk'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $enrolledStudent->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $publishedEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
        ]);

        foreach ([$enrolledStudent, $sameProgramUnenrolledStudent] as $student) {
            foreach (range(1, 2) as $day) {
                Attendance::create([
                    'student_id' => $student->id,
                    'timetable_entry_id' => $publishedEntry->id,
                    'date' => now()->subDays($day)->toDateString(),
                    'status' => 'absent',
                ]);
            }
        }

        $data = app(AcademicCourseDeliveryService::class)->attendanceInterventions($teacherUser);
        $titles = collect($data['items'])->pluck('title');

        $this->assertTrue($titles->contains('Assigned Subject Attendance Risk'));
        $this->assertFalse($titles->contains('Same Program Unassigned Attendance Risk'));
    }

    public function test_course_delivery_attendance_interventions_ignore_draft_timetable_history(): void
    {
        $fixture = $this->seedCourseFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $course = Course::factory()->create(['department_id' => $fixture['department']->id]);
        $semester = Semester::where('is_current', true)->firstOrFail();
        $teacher = Teacher::factory()->create(['department_id' => $fixture['department']->id]);

        $officialStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Official Course Delivery Risk'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);
        $draftOnlyStudent = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Draft Course Delivery Risk'])->id,
            'department_id' => $fixture['department']->id,
            'program_id' => $fixture['program']->id,
            'status' => 'active',
        ]);

        $publishedEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
        ]);
        foreach (range(1, 2) as $day) {
            Attendance::create([
                'student_id' => $officialStudent->id,
                'timetable_entry_id' => $publishedEntry->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => 'absent',
            ]);
        }

        $draftEntry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'draft',
        ]);
        foreach (range(1, 2) as $day) {
            Attendance::create([
                'student_id' => $draftOnlyStudent->id,
                'timetable_entry_id' => $draftEntry->id,
                'date' => now()->subDays($day + 3)->toDateString(),
                'status' => 'absent',
            ]);
        }

        $data = app(AcademicCourseDeliveryService::class)->attendanceInterventions($dean);
        $titles = collect($data['items'])->pluck('title');

        $this->assertTrue($titles->contains('Official Course Delivery Risk'));
        $this->assertFalse($titles->contains('Draft Course Delivery Risk'));
    }

    public function test_course_delivery_session_metrics_treat_draft_version_entries_as_unpublished(): void
    {
        $fixture = $this->seedCourseFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $course = Course::factory()->create(['department_id' => $fixture['department']->id]);
        $semester = Semester::where('is_current', true)->firstOrFail();
        $term = \App\Models\Term::factory()->create(['program_id' => $fixture['program']->id]);
        $teacher = Teacher::factory()->create(['department_id' => $fixture['department']->id]);
        $service = app(AcademicCourseDeliveryService::class);
        $before = $service->sessionDelivery($dean)['metrics'];

        $publishedVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $dean->id,
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 1])->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $publishedVersion->id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $dean->id,
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'subject_id' => $fixture['subject']->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['start_time' => '09:00:00', 'end_time' => '10:00:00', 'sort_order' => 2])->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);

        $after = $service->sessionDelivery($dean)['metrics'];

        $this->assertSame($before['published_sessions'] + 1, $after['published_sessions']);
        $this->assertSame($before['draft_sessions'] + 1, $after['draft_sessions']);
        $this->assertSame($before['today_sessions'] + 1, $after['today_sessions']);
    }

    public function test_course_delivery_session_metrics_prefer_canonical_pmc_official_sessions(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-06-22 09:00:00'));

        $fixture = $this->seedCourseFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $batch = Batch::factory()->create(['program_id' => $fixture['program']->id]);
        $term = \App\Models\Term::factory()->create([
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
        ]);
        $teacher = Teacher::factory()->create(['department_id' => $fixture['department']->id]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Course Delivery Official Group',
            'group_type' => 'core_section',
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $fixture['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 35,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $version = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $dean->id,
            'published_by' => $dean->id,
            'published_at' => now(),
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $dean->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Course Delivery Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $dean->id,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        $officialRoom = Classroom::factory()->create(['name' => 'Official Delivery Room']);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $fixture['subject']->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $officialRoom->id,
            'day_of_week' => now()->dayOfWeekIso,
            'timetable_slot_id' => TimetableSlot::factory()->create(['name' => 'Official Delivery Slot', 'sort_order' => 1])->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $dean->id,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $draftVersion->id,
            'course_group_id' => $group->id,
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => Subject::factory()->create(['program_id' => $fixture['program']->id, 'name' => 'Draft Delivery Canonical Subject'])->id,
            'session_index' => 2,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => Classroom::factory()->create(['name' => 'Draft Delivery Canonical Room'])->id,
            'day_of_week' => now()->dayOfWeekIso,
            'timetable_slot_id' => TimetableSlot::factory()->create(['sort_order' => 2])->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => Semester::where('is_current', true)->firstOrFail()->id,
            'course_id' => Course::factory()->create(['department_id' => $fixture['department']->id])->id,
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'subject_id' => Subject::factory()->create(['program_id' => $fixture['program']->id, 'name' => 'Legacy Delivery Flattened Subject'])->id,
            'teacher_id' => $teacher->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['sort_order' => 3])->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
            'status' => 'published',
        ]);

        $data = app(AcademicCourseDeliveryService::class)->sessionDelivery($dean);
        $titles = collect($data['items'])->pluck('title');
        $subtitles = collect($data['items'])->pluck('subtitle')->join(' | ');

        $this->assertSame(1, $data['metrics']['today_sessions']);
        $this->assertSame(1, $data['metrics']['published_sessions']);
        $this->assertSame(0, $data['metrics']['draft_sessions']);
        $this->assertTrue($titles->contains('Management Foundations'));
        $this->assertStringContainsString('Official Delivery Room', $subtitles);
        $this->assertStringContainsString('Course Delivery Official Group', $subtitles);
        $this->assertFalse($titles->contains('Legacy Delivery Flattened Subject'));
        $this->assertFalse($titles->contains('Draft Delivery Canonical Subject'));
    }

    public function test_non_academic_user_cannot_access_course_delivery_os(): void
    {
        $this->seedCourseFixture();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('student');

        $this->actingAs($user)
            ->get(route('academics.course-delivery.index'))
            ->assertForbidden();
    }
}
