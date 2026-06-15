<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV071Test extends TestCase
{
    use RefreshDatabase;

    public function test_publish_notifications_include_impact_audience_metadata(): void
    {
        $department = Department::factory()->create(['code' => 'V071D', 'name' => 'V071 Timetable Department']);
        Program::factory()->create(['department_id' => $department->id, 'code' => 'BASE', 'name' => 'Base Program', 'is_active' => true]);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);
        Classroom::query()->update(['is_active' => false]);

        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V071', 'name' => 'V071 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V071-26', 'name' => 'V071 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V071 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V071101', 'name' => 'Notification Impact', 'credits' => 2, 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $teacherUser = User::factory()->create(['name' => 'V071 Faculty', 'email' => 'v071.faculty@example.com', 'password' => bcrypt('password')]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $department->id, 'employee_id' => 'V071-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Notification Impact', 'status' => 'active']);
        Classroom::create(['room_number' => 'V071-101', 'name' => 'V071 Room', 'capacity' => 80, 'type' => 'lecture', 'is_active' => true]);
        foreach ([1, 2] as $index) {
            TimetableSlot::create(['name' => 'V071 Period ' . $index, 'start_time' => sprintf('%02d:00', 8 + $index), 'end_time' => sprintf('%02d:00', 9 + $index), 'is_break' => false, 'sort_order' => 9990 + $index, 'is_active' => true]);
        }

        $group = AcademicPmcCourseGroup::create([
            'name' => 'V071 Core Section A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 80,
            'current_strength' => 40,
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

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'V071 Notification Impact Timetable',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'V071 Notification Impact Timetable')->firstOrFail();
        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.publish', $run), [
            'decision_reason' => 'Publish with audience-aware notification metadata.',
            'effective_from' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $studentNotice = AcademicPmcTimetableNotification::where('notification_type', 'publish')->where('recipient_type', 'students')->latest('id')->firstOrFail();
        $facultyNotice = AcademicPmcTimetableNotification::where('notification_type', 'publish')->where('recipient_type', 'faculty')->latest('id')->firstOrFail();

        $this->assertSame('PMC OS v0.071', $studentNotice->metadata['version']);
        $this->assertSame($run->id, $studentNotice->metadata['generation_run_id']);
        $this->assertSame(7, $studentNotice->metadata['impact_preview']['impact_records']);
        $this->assertSame(0, $studentNotice->metadata['audience_count']);
        $this->assertSame(1, $facultyNotice->metadata['audience_count']);
        $this->assertSame(1, $facultyNotice->metadata['impact_preview']['affected_faculty']);
        $this->assertSame($run->quality_score, $facultyNotice->metadata['quality_score']);
    }
}
