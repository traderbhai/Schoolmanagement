<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\AcademicPmcTimetableV041Service;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV063Test extends TestCase
{
    use RefreshDatabase;

    public function test_quality_flags_consecutive_faculty_pressure_group_daily_load_and_student_gaps(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);
        Classroom::query()->update(['is_active' => false]);

        $department = Department::factory()->create(['code' => 'V063D', 'name' => 'V063 Timetable Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V063', 'name' => 'V063 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V063-26', 'name' => 'V063 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V063 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V063101', 'name' => 'Timetable Quality', 'credits' => 3, 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $teacherOneUser = User::factory()->create(['name' => 'V063 Faculty One', 'email' => 'v063.faculty.one@example.com', 'password' => bcrypt('password')]);
        $teacherTwoUser = User::factory()->create(['name' => 'V063 Faculty Two', 'email' => 'v063.faculty.two@example.com', 'password' => bcrypt('password')]);
        $teacherOne = Teacher::create(['user_id' => $teacherOneUser->id, 'department_id' => $department->id, 'employee_id' => 'V063-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Scheduling', 'status' => 'active']);
        $teacherTwo = Teacher::create(['user_id' => $teacherTwoUser->id, 'department_id' => $department->id, 'employee_id' => 'V063-FAC-002', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Scheduling', 'status' => 'active']);

        $slots = collect(range(1, 5))->map(fn ($index) => TimetableSlot::create([
            'name' => 'V063 Period ' . $index,
            'start_time' => sprintf('%02d:00', 8 + $index),
            'end_time' => sprintf('%02d:00', 9 + $index),
            'is_break' => false,
            'sort_order' => 9500 + $index,
            'is_active' => true,
        ]));
        $room = Classroom::create(['room_number' => 'V063-101', 'name' => 'V063 Lecture Room', 'capacity' => 80, 'type' => 'lecture', 'is_active' => true]);

        $packedGroup = AcademicPmcCourseGroup::create([
            'name' => 'V063 Packed Core Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 80,
            'current_strength' => 50,
            'status' => 'ready',
            'constraints' => ['max_student_classes_per_day' => 2],
        ]);
        $gapGroup = AcademicPmcCourseGroup::create([
            'name' => 'V063 Gapped Elective Group',
            'group_type' => 'elective_group',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 80,
            'current_strength' => 40,
            'status' => 'ready',
            'constraints' => ['max_student_classes_per_day' => 4],
        ]);

        AcademicPmcFacultyPreference::create([
            'teacher_id' => $teacherOne->id,
            'term_id' => $term->id,
            'faculty_type' => 'regular',
            'available_days' => [1, 2, 3, 4, 5],
            'preferred_slots' => [],
            'unavailable_slots' => [],
            'max_classes_per_day' => 5,
            'max_consecutive_classes' => 2,
            'max_weekly_load' => 18,
        ]);

        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'V063 Quality Warning Timetable',
            'strategy' => 'manual_quality_test',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'created_by' => $chair->id,
            'status' => 'generated',
            'input_summary' => ['version' => 'PMC OS v0.063'],
        ]);

        foreach ([0, 1, 2] as $index) {
            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'course_group_id' => $packedGroup->id,
                'session_type' => 'lecture',
                'duration_slots' => 1,
                'teacher_id' => $teacherOne->id,
                'classroom_id' => $room->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $slots[$index]->id,
                'status' => 'scheduled',
                'explanation' => 'Packed day quality test.',
            ]);
        }

        foreach ([0, 4] as $index) {
            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'course_group_id' => $gapGroup->id,
                'session_type' => 'lecture',
                'duration_slots' => 1,
                'teacher_id' => $teacherTwo->id,
                'classroom_id' => $room->id,
                'day_of_week' => 2,
                'timetable_slot_id' => $slots[$index]->id,
                'status' => 'scheduled',
                'explanation' => 'Student gap quality test.',
            ]);
        }

        $quality = app(AcademicPmcTimetableV041Service::class)->refreshConstraintsAndQuality($run);

        $this->assertTrue(AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->where('constraint_type', 'faculty_consecutive_load')->exists());
        $this->assertTrue(AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->where('constraint_type', 'student_group_daily_load')->exists());
        $this->assertTrue(AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->where('constraint_type', 'student_group_day_gaps')->exists());
        $this->assertSame('PMC OS v0.063', $quality->details['version']);
        $this->assertTrue($quality->details['faculty_consecutive_checked']);
        $this->assertTrue($quality->details['student_group_day_gaps_checked']);
        $this->assertGreaterThanOrEqual(3, $run->fresh()->soft_warning_count);
    }
}
