<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherTimetablePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_timetable_uses_teachers_current_official_term_not_global_future_term(): void
    {
        $this->travelTo('2026-07-14 09:00:00');

        [$teacher, $item] = $this->officialPmcSessionFixture();

        Term::factory()->create([
            'program_id' => $item->program_id,
            'batch_id' => $item->batch_id,
            'term_number' => 4,
            'name' => 'Future Semester IV',
            'start_date' => '2028-01-01',
            'end_date' => '2028-05-31',
            'is_current' => false,
        ]);

        $this->actingAs($teacher->user)
            ->get(route('teacher.timetable.index'))
            ->assertOk()
            ->assertSee('Official PMC Portal Subject')
            ->assertSee('Portal Section A')
            ->assertDontSee('Future Semester IV');
    }

    public function test_teacher_attendance_can_mark_bridged_pmc_session_when_semester_ids_differ(): void
    {
        $this->travelTo('2026-07-14 09:00:00');

        [$teacher, $item, $student] = $this->officialPmcSessionFixture();

        Semester::factory()->create([
            'number' => 1,
            'name' => 'Older Semester I',
            'is_current' => false,
        ]);
        Semester::factory()->create([
            'number' => 1,
            'name' => 'Current Semester I',
            'is_current' => true,
        ]);

        $this->actingAs($teacher->user)
            ->get(route('teacher.attendance.mark', ['date' => '2026-07-14']))
            ->assertOk()
            ->assertSee('Official PMC Portal Subject')
            ->assertDontSee('No published classes are scheduled');

        $entry = TimetableEntry::where('pmc_generation_item_id', $item->id)->firstOrFail();

        $this->actingAs($teacher->user)
            ->get(route('teacher.attendance.mark', [
                'date' => '2026-07-14',
                'entry_id' => $entry->id,
            ]))
            ->assertOk()
            ->assertSee('Portal Student');

        $this->actingAs($teacher->user)
            ->post(route('teacher.attendance.store'), [
                'timetable_entry_id' => $entry->id,
                'date' => '2026-07-14',
                'attendance' => [$student->id => 'present'],
            ])
            ->assertRedirect(route('teacher.attendance.mark'));

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'timetable_entry_id' => $entry->id,
            'pmc_generation_item_id' => $item->id,
            'status' => 'present',
        ]);
    }

    private function officialPmcSessionFixture(): array
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create(['name' => 'Portal Teacher']);
        $teacherUser->assignRole('teacher');
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id, 'status' => 'active']);

        $studentUser = User::factory()->create(['name' => 'Portal Student']);
        $studentUser->assignRole('student');

        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $course = Course::factory()->create();
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Semester I',
            'start_date' => '2026-07-01',
            'end_date' => '2026-11-30',
            'is_current' => true,
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Official PMC Portal Subject',
            'term_number' => 1,
        ]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Portal Section A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 1,
            'status' => 'active',
            'is_locked' => true,
        ]);
        AcademicPmcCourseGroupMember::create([
            'course_group_id' => $group->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $teacherUser->id,
            'published_by' => $teacherUser->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Portal Timetable Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $teacherUser->id,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);

        $item = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => Classroom::factory()->create(['name' => 'Portal Room'])->id,
            'day_of_week' => 2,
            'timetable_slot_id' => TimetableSlot::factory()->create(['name' => 'Portal Period', 'sort_order' => 1])->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $teacherUser->id,
        ]);

        return [$teacher, $item, $student];
    }
}
