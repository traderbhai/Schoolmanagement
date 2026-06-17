<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\RoleProgramAssignment;
use App\Models\Semester;
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

class ProgramChairLegacyTimetableIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function chair(?Program $program = null): User
    {
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('program_chair');
        $assigner = User::factory()->create();

        if ($program) {
            RoleProgramAssignment::create([
                'user_id' => $user->id,
                'role_name' => 'program_chair',
                'program_id' => $program->id,
                'is_active' => true,
                'assigned_by' => $assigner->id,
                'assigned_at' => now(),
            ]);
        }

        return $user;
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function academicSet(): array
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term I',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(4),
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'department_id' => $program->department_id,
        ]);
        $teacher = Teacher::factory()->create();
        $room = Classroom::factory()->create();
        $slot = TimetableSlot::factory()->create();

        return compact('program', 'batch', 'term', 'subject', 'teacher', 'room', 'slot');
    }

    private function saveSlotPayload(array $set, array $override = []): array
    {
        return array_merge([
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
        ], $override);
    }

    public function test_program_chair_without_assignment_cannot_write_any_program_timetable(): void
    {
        $set = $this->academicSet();

        $this->actingAs($this->chair())
            ->post(route('chair.timetable.save-slot'), $this->saveSlotPayload($set))
            ->assertForbidden();

        $this->assertDatabaseCount('timetable_entries', 0);
    }

    public function test_program_chair_cannot_save_slot_outside_assigned_program(): void
    {
        $assigned = $this->academicSet();
        $other = $this->academicSet();

        $this->actingAs($this->chair($assigned['program']))
            ->post(route('chair.timetable.save-slot'), $this->saveSlotPayload($other))
            ->assertForbidden();

        $this->assertDatabaseCount('timetable_entries', 0);
    }

    public function test_legacy_save_slot_creates_operational_entry_with_required_legacy_fields(): void
    {
        $set = $this->academicSet();

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.save-slot'), $this->saveSlotPayload($set))
            ->assertOk()
            ->assertJsonFragment(['message' => 'Saved.']);

        $entry = TimetableEntry::where('program_id', $set['program']->id)
            ->where('term_id', $set['term']->id)
            ->where('batch_id', $set['batch']->id)
            ->where('subject_id', $set['subject']->id)
            ->firstOrFail();

        $this->assertSame($set['teacher']->id, $entry->teacher_id);
        $this->assertSame($set['room']->id, $entry->classroom_id);
        $this->assertNotNull($entry->semester_id);
        $this->assertNotNull($entry->course_id);
        $this->assertTrue(Semester::whereKey($entry->semester_id)->exists());
        $this->assertDatabaseHas('courses', [
            'id' => $entry->course_id,
            'code' => 'PMCP' . $set['program']->id,
        ]);
    }

    public function test_legacy_save_slot_is_blocked_after_timetable_is_published(): void
    {
        $set = $this->academicSet();

        TimetableVersion::create([
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $this->admin()->id,
            'published_by' => $this->admin()->id,
            'published_at' => now(),
            'effective_from' => now()->toDateString(),
        ]);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.save-slot'), $this->saveSlotPayload($set))
            ->assertStatus(423)
            ->assertJsonFragment(['message' => 'Published timetable history is locked on legacy Program Chair routes. Use PMC timetable revision/version workflow for changes.']);

        $this->assertDatabaseCount('timetable_entries', 0);
    }

    public function test_legacy_publish_is_blocked_when_published_version_already_exists(): void
    {
        $set = $this->academicSet();

        TimetableVersion::create([
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $this->admin()->id,
            'published_by' => $this->admin()->id,
            'published_at' => now(),
            'effective_from' => now()->toDateString(),
        ]);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.publish'), [
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
                'batch_id' => $set['batch']->id,
                'effective_from' => now()->toDateString(),
            ])
            ->assertSessionHas('error', 'Published timetable history is locked on legacy Program Chair routes. Use PMC timetable revision/version workflow for changes.');

        $this->assertSame(1, TimetableVersion::where('program_id', $set['program']->id)->where('status', 'published')->count());
    }

    public function test_auto_schedule_acceptance_rejects_nested_out_of_program_suggestions(): void
    {
        $assigned = $this->academicSet();
        $other = $this->academicSet();

        $this->actingAs($this->chair($assigned['program']))
            ->post(route('chair.timetable.accept-auto-schedule'), [
                'program_id' => $assigned['program']->id,
                'term_id' => $assigned['term']->id,
                'suggestions' => [[
                    'subject_id' => $other['subject']->id,
                    'batch_id' => $assigned['batch']->id,
                    'teacher_id' => $assigned['teacher']->id,
                    'classroom_id' => $assigned['room']->id,
                    'day_of_week' => 1,
                    'timetable_slot_id' => $assigned['slot']->id,
                ]],
            ])
            ->assertSessionHas('error', 'Suggested subject does not belong to the selected program.');

        $this->assertDatabaseCount('timetable_entries', 0);
    }
}
