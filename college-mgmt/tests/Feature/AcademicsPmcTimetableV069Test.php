<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV069Test extends TestCase
{
    use RefreshDatabase;

    public function test_pmc_can_refresh_source_backed_generation_impact_preview_before_publish(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);
        Classroom::query()->update(['is_active' => false]);

        $department = Department::factory()->create(['code' => 'V069D', 'name' => 'V069 Timetable Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V069', 'name' => 'V069 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V069-26', 'name' => 'V069 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V069 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V069101', 'name' => 'Impact Preview', 'credits' => 1, 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $teacherUser = User::factory()->create(['name' => 'V069 Faculty', 'email' => 'v069.faculty@example.com', 'password' => bcrypt('password')]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $department->id, 'employee_id' => 'V069-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Impact Review', 'status' => 'active']);
        $slot = TimetableSlot::create(['name' => 'V069 Period 1', 'start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 9971, 'is_active' => true]);
        $room = Classroom::create(['room_number' => 'V069-101', 'name' => 'V069 Room', 'capacity' => 60, 'type' => 'lecture', 'is_active' => true]);
        $group = AcademicPmcCourseGroup::create(['name' => 'V069 Section A', 'group_type' => 'core_section', 'program_id' => $program->id, 'batch_id' => $batch->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'owner_user_id' => $chair->id, 'min_capacity' => 1, 'max_capacity' => 60, 'current_strength' => 2, 'status' => 'ready']);

        foreach (['A', 'B'] as $suffix) {
            $studentUser = User::factory()->create(['name' => 'V069 Student ' . $suffix, 'email' => 'v069.student.' . strtolower($suffix) . '@example.com', 'password' => bcrypt('password')]);
            $student = Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);
            AcademicPmcCourseGroupMember::create(['course_group_id' => $group->id, 'student_id' => $student->id, 'status' => 'active']);
        }

        $run = AcademicPmcTimetableGenerationRun::create(['title' => 'V069 Impact Timetable', 'strategy' => 'manual', 'program_id' => $program->id, 'batch_id' => $batch->id, 'term_id' => $term->id, 'created_by' => $chair->id, 'status' => 'generated', 'input_summary' => ['version' => 'PMC OS v0.069']]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $group->id,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'confidence' => 90,
            'metadata' => ['previous_placement' => ['day' => 2, 'slot_id' => $slot->id], 'manual_move' => ['decision_note' => 'Impact test move']],
        ]);
        AcademicPmcTimetableConstraint::create([
            'generation_run_id' => $run->id,
            'constraint_type' => 'faculty_consecutive_load',
            'severity' => 'soft',
            'title' => 'Faculty consecutive load warning',
            'description' => 'Impact preview should include this warning.',
            'affected_type' => 'teacher',
            'affected_key' => (string) $teacher->id,
            'recommended_fix' => 'Move one session.',
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.impact-preview', $run))
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(7, AcademicPmcTimetableImpactRecord::where('metadata->generation_run_id', $run->id)->where('metadata->version', 'PMC OS v0.069')->count());
        $this->assertSame(2, AcademicPmcTimetableImpactRecord::where('impact_type', 'students')->where('metadata->generation_run_id', $run->id)->value('affected_count'));
        $this->assertSame(1, AcademicPmcTimetableImpactRecord::where('impact_type', 'faculty')->where('metadata->generation_run_id', $run->id)->value('affected_count'));
        $this->assertSame(1, AcademicPmcTimetableImpactRecord::where('impact_type', 'rooms')->where('metadata->generation_run_id', $run->id)->value('affected_count'));
        $this->assertSame(1, AcademicPmcTimetableImpactRecord::where('impact_type', 'changed_slots')->where('metadata->generation_run_id', $run->id)->value('affected_count'));
        $this->assertSame(1, AcademicPmcTimetableImpactRecord::where('impact_type', 'conflicts')->where('metadata->generation_run_id', $run->id)->value('affected_count'));
        $this->assertSame(3, AcademicPmcTimetableImpactRecord::where('impact_type', 'notification_audience')->where('metadata->generation_run_id', $run->id)->value('affected_count'));
        $this->assertSame(2, $run->input_summary['impact_preview']['affected_students']);
        $this->assertTrue(DepartmentActivityLog::where('action', 'academic_pmc_v069_timetable_impact_preview_refreshed')->exists());

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-generator.index'))
            ->assertOk()
            ->assertSee('Pre-Publish Impact Preview')
            ->assertSee('Students affected by generated timetable')
            ->assertSee('Notification audience before publish/revision');
    }
}
