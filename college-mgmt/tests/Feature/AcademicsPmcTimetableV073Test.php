<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV073Test extends TestCase
{
    use RefreshDatabase;

    public function test_publish_creates_individual_student_and_faculty_notification_records(): void
    {
        $department = Department::factory()->create(['code' => 'V073D', 'name' => 'V073 Timetable Department']);
        Program::factory()->create(['department_id' => $department->id, 'code' => 'BASE', 'name' => 'Base Program', 'is_active' => true]);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);
        Classroom::query()->update(['is_active' => false]);

        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V073', 'name' => 'V073 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V073-26', 'name' => 'V073 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V073 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V073101', 'name' => 'Individual Publish Notices', 'credits' => 2, 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $teacherUser = User::factory()->create(['name' => 'V073 Faculty', 'email' => 'v073.faculty@example.com', 'password' => bcrypt('password')]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $department->id, 'employee_id' => 'V073-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Recipient Notices', 'status' => 'active']);
        Classroom::create(['room_number' => 'V073-101', 'name' => 'V073 Room', 'capacity' => 80, 'type' => 'lecture', 'is_active' => true]);
        foreach ([1, 2] as $index) {
            TimetableSlot::create(['name' => 'V073 Period ' . $index, 'start_time' => sprintf('%02d:00', 8 + $index), 'end_time' => sprintf('%02d:00', 9 + $index), 'is_break' => false, 'sort_order' => 10000 + $index, 'is_active' => true]);
        }

        $group = AcademicPmcCourseGroup::create([
            'name' => 'V073 Core Section A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 80,
            'current_strength' => 2,
            'status' => 'ready',
            'constraints' => ['weekly_hours' => 2],
        ]);
        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
            'assignment_source' => 'pmc',
            'approval_status' => 'pmc_approved',
            'weekly_hours' => 2,
            'assigned_by' => $chair->id,
        ]);

        $studentUsers = collect(['A', 'B'])->map(function (string $suffix) use ($department, $program, $batch, $group) {
            $user = User::factory()->create(['name' => 'V073 Student ' . $suffix, 'email' => 'v073.student.' . strtolower($suffix) . '@example.com', 'password' => bcrypt('password')]);
            $student = Student::factory()->create(['user_id' => $user->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);
            AcademicPmcCourseGroupMember::create(['course_group_id' => $group->id, 'student_id' => $student->id, 'status' => 'active']);

            return $user;
        });

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'V073 Recipient Notification Timetable',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'V073 Recipient Notification Timetable')->firstOrFail();
        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.publish', $run), [
            'decision_reason' => 'Publish with individual notification audit.',
            'effective_from' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $facultyNotice = AcademicPmcTimetableNotification::where('notification_type', 'publish')
            ->where('recipient_type', 'faculty')
            ->where('recipient_user_id', $teacherUser->id)
            ->firstOrFail();
        $this->assertSame('PMC OS v0.073', $facultyNotice->metadata['version']);
        $this->assertSame('individual_faculty', $facultyNotice->metadata['recipient_scope']);
        $this->assertSame([$group->id], $facultyNotice->metadata['course_group_ids']);

        foreach ($studentUsers as $studentUser) {
            $notice = AcademicPmcTimetableNotification::where('notification_type', 'publish')
                ->where('recipient_type', 'student')
                ->where('recipient_user_id', $studentUser->id)
                ->firstOrFail();
            $this->assertSame('PMC OS v0.073', $notice->metadata['version']);
            $this->assertSame('individual_student', $notice->metadata['recipient_scope']);
            $this->assertSame([$group->id], $notice->metadata['course_group_ids']);
        }

        $this->assertSame(2, AcademicPmcTimetableNotification::where('notification_type', 'publish')->where('recipient_type', 'student')->whereNotNull('recipient_user_id')->count());
        $this->assertSame(1, AcademicPmcTimetableNotification::where('notification_type', 'publish')->where('recipient_type', 'faculty')->whereNotNull('recipient_user_id')->count());
        $this->assertTrue(AcademicPmcTimetableNotification::where('notification_type', 'publish')->where('recipient_type', 'students')->whereNull('recipient_user_id')->exists());
    }
}
