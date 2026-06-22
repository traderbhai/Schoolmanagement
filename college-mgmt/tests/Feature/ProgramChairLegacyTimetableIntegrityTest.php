<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Classroom;
use App\Models\Course;
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
use App\Services\TimetableCopyService;
use App\Services\TimetableImportService;
use App\Services\TimetablePdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_save_slot_with_course_group_preserves_parallel_canonical_sessions(): void
    {
        $set = $this->academicSet();
        $groupA = AcademicPmcCourseGroup::create([
            'name' => 'Manual Section A',
            'group_type' => 'core_section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 20,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $subjectB = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
        ]);
        $groupB = AcademicPmcCourseGroup::create([
            'name' => 'Manual Section B',
            'group_type' => 'core_section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $subjectB->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 20,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Existing Manual Parallel Run',
            'strategy' => 'manual',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'created_by' => $this->admin()->id,
            'status' => 'draft',
            'scheduled_count' => 1,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $groupA->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'scheduled',
            'official_status' => 'draft',
            'source_type' => 'manual',
        ]);
        $teacherB = Teacher::factory()->create();
        $roomB = Classroom::factory()->create();

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.save-slot'), $this->saveSlotPayload($set, [
                'subject_id' => $subjectB->id,
                'teacher_id' => $teacherB->id,
                'classroom_id' => $roomB->id,
                'course_group_id' => $groupB->id,
            ]))
            ->assertOk()
            ->assertJson(['message' => 'Saved.']);

        $this->assertDatabaseCount('timetable_entries', 0);
        $this->assertSame(2, AcademicPmcTimetableGenerationItem::where('term_id', $set['term']->id)->count());
        $this->assertDatabaseHas('academic_pmc_timetable_generation_items', [
            'course_group_id' => $groupA->id,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'source_type' => 'manual',
        ]);
        $this->assertDatabaseHas('academic_pmc_timetable_generation_items', [
            'course_group_id' => $groupB->id,
            'subject_id' => $subjectB->id,
            'teacher_id' => $teacherB->id,
            'classroom_id' => $roomB->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'scheduled',
            'official_status' => 'draft',
            'source_type' => 'manual_slot_edit',
        ]);
    }

    public function test_publish_marks_group_only_parallel_canonical_sessions_official_and_bridges_distinct_rows(): void
    {
        $set = $this->academicSet();
        $subjectB = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
        ]);
        $groupA = AcademicPmcCourseGroup::create([
            'name' => 'Publish Section A',
            'group_type' => 'core_section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 20,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $groupB = AcademicPmcCourseGroup::create([
            'name' => 'Publish Elective B',
            'group_type' => 'elective_group',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $subjectB->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 15,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Legacy Publish Canonical Run',
            'strategy' => 'manual',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'created_by' => $this->admin()->id,
            'status' => 'draft',
            'scheduled_count' => 2,
        ]);

        $items = collect([
            [$groupA, $set['subject'], $set['teacher'], $set['room']],
            [$groupB, $subjectB, Teacher::factory()->create(), Classroom::factory()->create()],
        ])->map(fn (array $row) => AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $row[0]->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $row[2]->id,
            'classroom_id' => $row[3]->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'scheduled',
            'official_status' => 'draft',
            'source_type' => 'manual',
        ]));

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.publish'), [
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
                'batch_id' => $set['batch']->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $version = TimetableVersion::where('program_id', $set['program']->id)
            ->where('term_id', $set['term']->id)
            ->where('batch_id', $set['batch']->id)
            ->where('status', 'published')
            ->firstOrFail();

        $publishedItems = AcademicPmcTimetableGenerationItem::whereIn('id', $items->pluck('id'))
            ->orderBy('course_group_id')
            ->get();

        $this->assertSame([$groupA->id, $groupB->id], $publishedItems->pluck('course_group_id')->all());
        $this->assertSame([$version->id], $publishedItems->pluck('timetable_version_id')->unique()->values()->all());
        $this->assertSame(['published'], $publishedItems->pluck('official_status')->unique()->values()->all());
        $this->assertSame([$set['program']->id], $publishedItems->pluck('program_id')->unique()->values()->all());
        $this->assertSame([$set['batch']->id], $publishedItems->pluck('batch_id')->unique()->values()->all());
        $this->assertSame([$set['term']->id], $publishedItems->pluck('term_id')->unique()->values()->all());
        $this->assertEqualsCanonicalizing([$set['subject']->id, $subjectB->id], $publishedItems->pluck('subject_id')->all());

        $bridgeRows = TimetableEntry::where('timetable_version_id', $version->id)
            ->whereIn('pmc_generation_item_id', $items->pluck('id'))
            ->get();

        $this->assertCount(2, $bridgeRows);
        $this->assertEqualsCanonicalizing($items->pluck('id')->all(), $bridgeRows->pluck('pmc_generation_item_id')->all());
        $this->assertSame(2, $bridgeRows->pluck('course_id')->unique()->count());
    }

    public function test_builder_renders_parallel_canonical_sessions_and_group_controls(): void
    {
        $set = $this->academicSet();
        $subjectB = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
        ]);
        $groupA = AcademicPmcCourseGroup::create([
            'name' => 'Builder Section A',
            'group_type' => 'core_section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 20,
            'status' => 'active',
        ]);
        $groupB = AcademicPmcCourseGroup::create([
            'name' => 'Builder Elective B',
            'group_type' => 'elective_group',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $subjectB->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 15,
            'status' => 'active',
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Builder Parallel Run',
            'strategy' => 'manual',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'created_by' => $this->admin()->id,
            'status' => 'draft',
            'scheduled_count' => 2,
        ]);

        foreach (
            [
                [$groupA, $set['subject'], $set['teacher'], $set['room'], 'scheduled', 'draft', true],
                [$groupB, $subjectB, Teacher::factory()->create(), Classroom::factory()->create(), 'locked', 'published', false],
            ] as [$group, $subject, $teacher, $room, $status, $officialStatus, $includeDirectScope]
        ) {
            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'course_group_id' => $group->id,
                'program_id' => $includeDirectScope ? $set['program']->id : null,
                'batch_id' => $includeDirectScope ? $set['batch']->id : null,
                'term_id' => $includeDirectScope ? $set['term']->id : null,
                'subject_id' => $includeDirectScope ? $subject->id : null,
                'session_index' => 1,
                'session_type' => 'lecture',
                'duration_slots' => 1,
                'teacher_id' => $teacher->id,
                'classroom_id' => $room->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $set['slot']->id,
                'status' => $status,
                'official_status' => $officialStatus,
                'source_type' => 'manual',
            ]);
        }

        $this->actingAs($this->chair($set['program']))
            ->get(route('chair.timetable.builder', [
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
                'batch_id' => $set['batch']->id,
            ]))
            ->assertOk()
            ->assertSee('Course Group / Section')
            ->assertSee('Builder Section A')
            ->assertSee('Builder Elective B')
            ->assertSee((string) $groupA->id)
            ->assertSee((string) $groupB->id);
    }

    public function test_builder_master_filters_scope_parallel_canonical_sessions(): void
    {
        $set = $this->academicSet();
        $subjectB = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
        ]);
        $groupA = AcademicPmcCourseGroup::create([
            'name' => 'Filter Section A',
            'group_type' => 'core_section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 20,
            'status' => 'active',
        ]);
        $groupB = AcademicPmcCourseGroup::create([
            'name' => 'Filter Lab B',
            'group_type' => 'lab_group',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $subjectB->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 15,
            'status' => 'active',
        ]);
        $teacherB = Teacher::factory()->create();
        $roomB = Classroom::factory()->create();
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Builder Filter Run',
            'strategy' => 'manual',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'created_by' => $this->admin()->id,
            'status' => 'draft',
            'scheduled_count' => 2,
        ]);

        foreach (
            [
                [$groupA, $set['subject'], $set['teacher'], $set['room'], 'lecture', 'scheduled', 'draft', true],
                [$groupB, $subjectB, $teacherB, $roomB, 'lab', 'locked', 'published', false],
            ] as [$group, $subject, $teacher, $room, $type, $status, $officialStatus, $includeDirectScope]
        ) {
            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'course_group_id' => $group->id,
                'program_id' => $includeDirectScope ? $set['program']->id : null,
                'batch_id' => $includeDirectScope ? $set['batch']->id : null,
                'term_id' => $includeDirectScope ? $set['term']->id : null,
                'subject_id' => $includeDirectScope ? $subject->id : null,
                'session_index' => 1,
                'session_type' => $type,
                'duration_slots' => $type === 'lab' ? 2 : 1,
                'teacher_id' => $teacher->id,
                'classroom_id' => $room->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $set['slot']->id,
                'status' => $status,
                'official_status' => $officialStatus,
                'source_type' => 'manual',
            ]);
        }

        $response = $this->actingAs($this->chair($set['program']))
            ->get(route('chair.timetable.builder', [
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
                'batch_id' => $set['batch']->id,
                'teacher_id' => $teacherB->id,
                'classroom_id' => $roomB->id,
                'course_group_id' => $groupB->id,
                'session_type' => 'lab',
                'timetable_status' => 'locked',
            ]))
            ->assertOk()
            ->assertSee('All Faculty')
            ->assertSee('All Rooms')
            ->assertSee('All Sections / Groups')
            ->assertSee('All Types')
            ->assertSee('All Statuses');

        $renderedItems = $response->viewData('canonicalEntries')
            ->flatMap(fn ($items) => $items)
            ->values();

        $this->assertCount(1, $renderedItems);
        $this->assertSame($groupB->id, $renderedItems->first()->course_group_id);
        $this->assertSame($teacherB->id, $renderedItems->first()->teacher_id);
        $this->assertSame($roomB->id, $renderedItems->first()->classroom_id);
        $this->assertSame('lab', $renderedItems->first()->session_type);
        $this->assertSame('locked', $renderedItems->first()->status);
    }

    public function test_canonical_conflict_check_ignores_same_group_slot_being_edited(): void
    {
        $set = $this->academicSet();
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Editable Section',
            'group_type' => 'core_section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 20,
            'status' => 'active',
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Self Edit Run',
            'strategy' => 'manual',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'created_by' => $this->admin()->id,
            'status' => 'draft',
            'scheduled_count' => 1,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $group->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'scheduled',
            'official_status' => 'draft',
            'source_type' => 'manual',
        ]);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.check-conflict'), [
                'course_group_id' => $group->id,
                'teacher_id' => $set['teacher']->id,
                'classroom_id' => $set['room']->id,
                'batch_id' => $set['batch']->id,
                'term_id' => $set['term']->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $set['slot']->id,
                'duration_slots' => 1,
            ])
            ->assertOk()
            ->assertJson(['conflicts' => []]);
    }

    public function test_save_slot_blocks_lab_group_in_non_lab_room(): void
    {
        $set = $this->academicSet();
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Manual Lab Group',
            'group_type' => 'lab_group',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 40,
            'current_strength' => 20,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $nonLabRoom = Classroom::factory()->create(['capacity' => 50, 'type' => 'lecture', 'has_lab' => false]);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.save-slot'), $this->saveSlotPayload($set, [
                'classroom_id' => $nonLabRoom->id,
                'course_group_id' => $group->id,
                'session_type' => 'lab',
                'duration_slots' => 2,
            ]))
            ->assertStatus(422)
            ->assertJsonFragment(['Lab/practical group requires a lab-capable room']);

        $this->assertDatabaseMissing('academic_pmc_timetable_generation_items', [
            'course_group_id' => $group->id,
            'classroom_id' => $nonLabRoom->id,
        ]);
    }

    public function test_canonical_conflict_check_blocks_candidate_duration_overlap(): void
    {
        $set = $this->academicSet();
        $slotTwo = TimetableSlot::factory()->create([
            'name' => 'Second Slot',
            'sort_order' => $set['slot']->sort_order + 1,
        ]);
        $groupA = AcademicPmcCourseGroup::create([
            'name' => 'Duration Section A',
            'group_type' => 'core_section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 20,
            'status' => 'active',
        ]);
        $subjectB = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
        ]);
        $groupB = AcademicPmcCourseGroup::create([
            'name' => 'Duration Section B',
            'group_type' => 'lab_group',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $subjectB->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 20,
            'status' => 'active',
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Duration Run',
            'strategy' => 'manual',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'created_by' => $this->admin()->id,
            'status' => 'draft',
            'scheduled_count' => 1,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $groupA->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slotTwo->id,
            'status' => 'scheduled',
            'official_status' => 'draft',
            'source_type' => 'manual',
        ]);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.check-conflict'), [
                'course_group_id' => $groupB->id,
                'teacher_id' => $set['teacher']->id,
                'classroom_id' => Classroom::factory()->create()->id,
                'batch_id' => $set['batch']->id,
                'term_id' => $set['term']->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $set['slot']->id,
                'duration_slots' => 2,
            ])
            ->assertOk()
            ->assertJsonFragment(['Teacher is already assigned to another class at this time']);
    }

    public function test_substitutions_page_uses_operational_empty_state_when_no_records_exist(): void
    {
        $set = $this->academicSet();

        $this->actingAs($this->chair($set['program']))
            ->get(route('chair.timetable.substitutions'))
            ->assertOk()
            ->assertSee('No substitution records yet')
            ->assertSee('Faculty replacements, cancellations, and rescheduled sessions will appear here')
            ->assertSee(route('chair.timetable.builder'), false)
            ->assertDontSee('No records yet.');
    }

    public function test_substitutions_page_lists_official_canonical_pmc_sessions(): void
    {
        $set = $this->academicSet();
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Substitution Section A',
            'group_type' => 'section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'status' => 'active',
        ]);
        $version = TimetableVersion::create([
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
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Official Substitution Run',
            'strategy' => 'manual',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'timetable_version_id' => $version->id,
            'created_by' => $this->admin()->id,
            'status' => 'published',
            'scheduled_count' => 1,
        ]);
        $item = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'manual',
        ]);

        $this->actingAs($this->chair($set['program']))
            ->get(route('chair.timetable.substitutions'))
            ->assertOk()
            ->assertSee('Official PMC sessions')
            ->assertSee('Substitution Section A')
            ->assertSee('pmc:' . $item->id, false);
    }

    public function test_canonical_substitution_records_recommendation_instead_of_legacy_row(): void
    {
        $set = $this->academicSet();
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Manual Substitute Group',
            'group_type' => 'section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'status' => 'active',
        ]);
        $version = TimetableVersion::create([
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
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Manual Substitute Run',
            'strategy' => 'manual',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'timetable_version_id' => $version->id,
            'created_by' => $this->admin()->id,
            'status' => 'published',
            'scheduled_count' => 1,
        ]);
        $item = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'manual',
        ]);
        $substitute = Teacher::factory()->create();

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.substitutions.store'), [
                'session_ref' => 'pmc:' . $item->id,
                'date' => now()->toDateString(),
                'action' => 'substitute',
                'substitute_teacher_id' => $substitute->id,
                'reason' => 'Faculty on leave',
            ])
            ->assertSessionHas('success', 'Canonical substitution recorded for the official PMC session.');

        $this->assertDatabaseCount('timetable_substitutions', 0);
        $this->assertDatabaseHas('academic_pmc_substitution_recommendations', [
            'pmc_generation_item_id' => $item->id,
            'course_group_id' => $group->id,
            'original_teacher_id' => $set['teacher']->id,
            'substitute_teacher_id' => $substitute->id,
            'status' => 'recorded',
            'score' => 100,
        ]);
    }

    public function test_canonical_cancellation_records_timetable_change_request(): void
    {
        $set = $this->academicSet();
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Cancellation Group',
            'group_type' => 'section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'status' => 'active',
        ]);
        $version = TimetableVersion::create([
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
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Cancellation Run',
            'strategy' => 'manual',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'timetable_version_id' => $version->id,
            'created_by' => $this->admin()->id,
            'status' => 'published',
            'scheduled_count' => 1,
        ]);
        $item = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'manual',
        ]);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.substitutions.store'), [
                'session_ref' => 'pmc:' . $item->id,
                'date' => now()->toDateString(),
                'action' => 'cancelled',
                'reason' => 'Institute event',
            ])
            ->assertSessionHas('success', 'Canonical timetable change request recorded for the official PMC session.');

        $this->assertDatabaseCount('timetable_substitutions', 0);
        $this->assertDatabaseHas('academic_pmc_timetable_change_requests', [
            'timetable_version_id' => $version->id,
            'pmc_generation_item_id' => $item->id,
            'change_type' => 'cancellation',
            'status' => 'requested',
            'reason' => 'Institute event',
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

    public function test_auto_schedule_acceptance_is_blocked_for_published_batch_timetable(): void
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
            ->post(route('chair.timetable.accept-auto-schedule'), [
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
                'suggestions' => [[
                    'subject_id' => $set['subject']->id,
                    'batch_id' => $set['batch']->id,
                    'teacher_id' => $set['teacher']->id,
                    'classroom_id' => $set['room']->id,
                    'day_of_week' => 1,
                    'timetable_slot_id' => $set['slot']->id,
                ]],
            ])
            ->assertSessionHas('error', 'Published timetable history is locked on legacy Program Chair routes. Use PMC timetable revision/version workflow for changes.');

        $this->assertDatabaseCount('timetable_entries', 0);
    }

    public function test_auto_schedule_acceptance_preserves_canonical_course_group_identity(): void
    {
        $set = $this->academicSet();
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Accepted Auto Group',
            'group_type' => 'elective_group',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 40,
            'current_strength' => 12,
            'status' => 'active',
            'is_locked' => true,
        ]);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.accept-auto-schedule'), [
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
                'suggestions' => [[
                    'subject_id' => $set['subject']->id,
                    'batch_id' => $set['batch']->id,
                    'course_group_id' => $group->id,
                    'group_type' => $group->group_type,
                    'teacher_id' => $set['teacher']->id,
                    'classroom_id' => $set['room']->id,
                    'day_of_week' => 1,
                    'timetable_slot_id' => $set['slot']->id,
                    'confidence' => 82.5,
                    'reason' => 'Canonical group suggestion',
                ]],
            ])
            ->assertSessionHas('success', 'Auto-scheduled 1 canonical PMC sessions. Review and publish through the PMC timetable workflow.');

        $this->assertDatabaseCount('timetable_entries', 0);
        $this->assertDatabaseHas('academic_pmc_timetable_generation_items', [
            'course_group_id' => $group->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'scheduled',
            'official_status' => 'draft',
            'source_type' => 'auto_schedule_acceptance',
        ]);
        $this->assertDatabaseHas('academic_pmc_timetable_generation_runs', [
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'strategy' => 'course_group_auto_schedule',
            'status' => 'draft',
            'scheduled_count' => 1,
        ]);
    }

    public function test_legacy_batch_pdf_export_respects_assigned_program_scope(): void
    {
        $assigned = $this->academicSet();
        $other = $this->academicSet();

        $this->actingAs($this->chair($assigned['program']))
            ->post(route('chair.timetable.export-batch-pdf'), [
                'program_id' => $other['program']->id,
                'term_id' => $other['term']->id,
                'batch_id' => $other['batch']->id,
            ])
            ->assertForbidden();
    }

    public function test_legacy_teacher_pdf_export_requires_visible_program_timetable_entries(): void
    {
        $assigned = $this->academicSet();
        $other = $this->academicSet();

        $this->actingAs($this->admin())
            ->post(route('chair.timetable.save-slot'), $this->saveSlotPayload($other))
            ->assertOk();

        $this->actingAs($this->chair($assigned['program']))
            ->post(route('chair.timetable.export-teacher-pdf'), [
                'term_id' => $other['term']->id,
                'teacher_id' => $other['teacher']->id,
            ])
            ->assertForbidden();
    }

    public function test_teacher_pdf_export_allows_visible_canonical_pmc_sessions(): void
    {
        $set = $this->academicSet();
        $subject = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
            'name' => 'Route Canonical PDF Subject',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Route PDF Section',
            'group_type' => 'section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);
        $version = TimetableVersion::create([
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'version_number' => 3,
            'status' => 'published',
            'created_by' => $this->admin()->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Route Canonical PDF Run',
            'strategy' => 'balanced',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'timetable_version_id' => $version->id,
            'created_by' => $this->admin()->id,
            'status' => 'published',
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'locked',
            'official_status' => 'published',
        ]);

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('A4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('pdf'));
        Pdf::shouldReceive('loadHTML')
            ->once()
            ->with(\Mockery::on(function (string $html): bool {
                $this->assertStringContainsString('Route Canonical PDF Subject', $html);
                $this->assertStringContainsString('Route PDF Section', $html);

                return true;
            }))
            ->andReturn($pdf);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.export-teacher-pdf'), [
                'term_id' => $set['term']->id,
                'teacher_id' => $set['teacher']->id,
            ])
            ->assertOk();
    }

    public function test_timetable_pdf_service_exports_only_official_published_entries(): void
    {
        $set = $this->academicSet();
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term I']);
        $course = Course::factory()->create(['name' => 'Timetable PDF Course']);
        $draftSlot = TimetableSlot::factory()->create(['name' => 'Draft Slot', 'sort_order' => 2]);
        $draftVersionSlot = TimetableSlot::factory()->create(['name' => 'Draft Version Slot', 'sort_order' => 3]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => $this->admin()->id,
        ]);

        $officialSubject = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
            'name' => 'Official PDF Subject',
        ]);
        $draftSubject = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
            'name' => 'Draft PDF Subject',
        ]);
        $draftVersionSubject = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
            'name' => 'Draft Version PDF Subject',
        ]);

        foreach ([
            [$officialSubject, $set['slot'], 'published', null, 1],
            [$draftSubject, $draftSlot, 'draft', null, 2],
            [$draftVersionSubject, $draftVersionSlot, 'published', $draftVersion->id, 3],
        ] as [$subject, $slot, $status, $versionId, $day]) {
            TimetableEntry::create([
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
                'batch_id' => $set['batch']->id,
                'subject_id' => $subject->id,
                'teacher_id' => $set['teacher']->id,
                'classroom_id' => $set['room']->id,
                'timetable_slot_id' => $slot->id,
                'day_of_week' => $day,
                'is_active' => true,
                'status' => $status,
                'timetable_version_id' => $versionId,
            ]);
        }

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->twice()->with('A4', 'landscape')->andReturnSelf();
        Pdf::shouldReceive('loadHTML')
            ->twice()
            ->with(\Mockery::on(function (string $html): bool {
                $this->assertStringContainsString('Official PDF Subject', $html);
                $this->assertStringNotContainsString('Draft PDF Subject', $html);
                $this->assertStringNotContainsString('Draft Version PDF Subject', $html);

                return true;
            }))
            ->andReturn($pdf);

        $service = app(TimetablePdfService::class);
        $service->generateBatchPdf($set['program']->id, $set['term']->id, $set['batch']->id);
        $service->generateTeacherPdf($set['term']->id, $set['teacher']->id, [$set['program']->id]);
    }

    public function test_timetable_pdf_service_prefers_parallel_canonical_pmc_sessions(): void
    {
        $set = $this->academicSet();
        $course = Course::factory()->create(['name' => 'Canonical PDF Course']);
        $subjectOne = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
            'name' => 'Canonical PDF Section A Subject',
        ]);
        $subjectTwo = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
            'name' => 'Canonical PDF Section B Subject',
        ]);
        $legacySubject = Subject::factory()->create([
            'program_id' => $set['program']->id,
            'department_id' => $set['program']->department_id,
            'name' => 'Legacy PDF Stale Subject',
        ]);
        $groupOne = AcademicPmcCourseGroup::create([
            'name' => 'PDF Section A',
            'group_type' => 'section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $subjectOne->id,
            'status' => 'active',
        ]);
        $groupTwo = AcademicPmcCourseGroup::create([
            'name' => 'PDF Section B',
            'group_type' => 'section',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $subjectTwo->id,
            'status' => 'active',
        ]);
        $version = TimetableVersion::create([
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'version_number' => 2,
            'status' => 'published',
            'created_by' => $this->admin()->id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Canonical PDF Run',
            'strategy' => 'balanced',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'timetable_version_id' => $version->id,
            'created_by' => $this->admin()->id,
            'status' => 'published',
        ]);
        $teacherTwo = Teacher::factory()->create(['user_id' => User::factory()->create(['name' => 'Canonical PDF Faculty Two'])->id]);
        $roomTwo = Classroom::factory()->create(['room_number' => 'PDF-202']);

        foreach ([
            [$groupOne, $subjectOne, $set['teacher'], $set['room']],
            [$groupTwo, $subjectTwo, $teacherTwo, $roomTwo],
        ] as [$group, $subject, $teacher, $room]) {
            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'timetable_version_id' => $version->id,
                'course_group_id' => $group->id,
                'session_type' => 'lecture',
                'duration_slots' => 1,
                'teacher_id' => $teacher->id,
                'classroom_id' => $room->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $set['slot']->id,
                'status' => 'locked',
                'official_status' => 'published',
            ]);
        }

        TimetableEntry::create([
            'semester_id' => Semester::factory()->create(['number' => 1, 'name' => 'Term I'])->id,
            'course_id' => $course->id,
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'subject_id' => $legacySubject->id,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'timetable_slot_id' => $set['slot']->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
        ]);

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('A4', 'landscape')->andReturnSelf();
        Pdf::shouldReceive('loadHTML')
            ->once()
            ->with(\Mockery::on(function (string $html): bool {
                $this->assertStringContainsString('Canonical PDF Section A Subject', $html);
                $this->assertStringContainsString('Canonical PDF Section B Subject', $html);
                $this->assertStringContainsString('PDF Section A', $html);
                $this->assertStringContainsString('PDF Section B', $html);
                $this->assertStringContainsString('PDF-202', $html);
                $this->assertStringNotContainsString('Legacy PDF Stale Subject', $html);

                return true;
            }))
            ->andReturn($pdf);

        $service = app(TimetablePdfService::class);
        $service->generateBatchPdf($set['program']->id, $set['term']->id, $set['batch']->id);
    }

    public function test_legacy_import_uses_term_scope_and_creates_required_bridge_fields(): void
    {
        $set = $this->academicSet();
        $csv = implode("\n", [
            'day_of_week,timetable_slot_id,subject_id,teacher_id,classroom_id',
            "1,{$set['slot']->id},{$set['subject']->id},{$set['teacher']->id},{$set['room']->id}",
        ]);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.do-import'), [
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
                'batch_id' => $set['batch']->id,
                'file' => UploadedFile::fake()->createWithContent('timetable.csv', $csv),
            ])
            ->assertSessionHas('success', 'Imported 1 timetable entries.');

        $entry = TimetableEntry::where('program_id', $set['program']->id)
            ->where('term_id', $set['term']->id)
            ->where('batch_id', $set['batch']->id)
            ->where('subject_id', $set['subject']->id)
            ->firstOrFail();

        $this->assertNotNull($entry->semester_id);
        $this->assertNotNull($entry->course_id);
        $this->assertSame('draft', $entry->status);
    }

    public function test_import_with_course_group_creates_canonical_pmc_generation_item(): void
    {
        $set = $this->academicSet();
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Imported Canonical Group',
            'group_type' => 'lab_group',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 30,
            'current_strength' => 18,
            'status' => 'active',
            'is_locked' => true,
        ]);

        $csv = implode("\n", [
            'day_of_week,timetable_slot_id,subject_id,teacher_id,classroom_id,course_group_id,session_type,duration_slots',
            "1,{$set['slot']->id},{$set['subject']->id},{$set['teacher']->id},{$set['room']->id},{$group->id},lab,2",
        ]);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.timetable.do-import'), [
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
                'batch_id' => $set['batch']->id,
                'file' => UploadedFile::fake()->createWithContent('canonical-timetable.csv', $csv),
            ])
            ->assertSessionHas('success', 'Imported 1 timetable entries.');

        $this->assertDatabaseCount('timetable_entries', 0);
        $this->assertDatabaseHas('academic_pmc_timetable_generation_items', [
            'course_group_id' => $group->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'session_type' => 'lab',
            'duration_slots' => 2,
            'status' => 'scheduled',
            'official_status' => 'draft',
            'source_type' => 'csv_import',
        ]);
        $this->assertDatabaseHas('academic_pmc_timetable_generation_runs', [
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'strategy' => 'csv_import',
            'status' => 'draft',
            'scheduled_count' => 1,
        ]);
    }

    public function test_copy_uses_canonical_pmc_generation_items_when_source_term_has_group_sessions(): void
    {
        $set = $this->academicSet();
        $targetTerm = Term::create([
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_number' => 2,
            'name' => 'Term II',
            'start_date' => now()->addMonths(5),
            'end_date' => now()->addMonths(9),
        ]);
        $sourceGroup = AcademicPmcCourseGroup::create([
            'name' => 'Copy Canonical Group',
            'group_type' => 'elective_group',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 40,
            'current_strength' => 15,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $targetGroup = AcademicPmcCourseGroup::create([
            'name' => 'Copy Canonical Group',
            'group_type' => 'elective_group',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $targetTerm->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 40,
            'current_strength' => 15,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Source Copy Run',
            'strategy' => 'source',
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $set['term']->id,
            'created_by' => $this->admin()->id,
            'status' => 'draft',
            'scheduled_count' => 1,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $sourceGroup->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'source',
        ]);

        $sources = collect(app(TimetableCopyService::class)->getAvailableSourceTerms($set['program']->id));
        $preview = app(TimetableCopyService::class)->previewCopy(
            $set['term']->id,
            $targetTerm->id,
            $set['program']->id,
            $set['batch']->id
        );

        $this->assertTrue($sources->contains('id', $set['term']->id));
        $this->assertSame(1, $preview['source_count']);
        $this->assertSame([$set['batch']->name], $preview['source_batches']);
        $this->assertSame('Copy Canonical Group', $preview['preview'][0]['course_group']);

        $copyResult = app(TimetableCopyService::class)->executeCopy(
            $set['term']->id,
            $targetTerm->id,
            $set['program']->id,
            $set['batch']->id,
            ['created_by' => $this->admin()->id]
        );

        $this->assertTrue($copyResult['success']);
        $this->assertSame(1, $copyResult['copied']);
        $this->assertDatabaseCount('timetable_entries', 0);
        $this->assertDatabaseHas('academic_pmc_timetable_generation_items', [
            'course_group_id' => $targetGroup->id,
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $targetTerm->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'status' => 'scheduled',
            'official_status' => 'draft',
            'source_type' => 'copy_from_previous_term',
        ]);
        $this->assertDatabaseHas('academic_pmc_timetable_generation_runs', [
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_id' => $targetTerm->id,
            'strategy' => 'copy_from_previous_term',
            'status' => 'draft',
            'scheduled_count' => 1,
        ]);
    }

    public function test_import_and_copy_replacement_do_not_delete_published_timetable_history(): void
    {
        $set = $this->academicSet();
        $targetTerm = Term::create([
            'program_id' => $set['program']->id,
            'batch_id' => $set['batch']->id,
            'term_number' => 2,
            'name' => 'Term II',
            'start_date' => now()->addMonths(5),
            'end_date' => now()->addMonths(9),
        ]);
        $sourceSemester = Semester::factory()->create(['number' => 1, 'name' => 'Term I Source']);
        $semester = Semester::factory()->create(['number' => 2, 'name' => 'Term II']);
        $course = Course::factory()->create(['name' => 'Timetable Import Bridge']);

        TimetableEntry::create([
            'semester_id' => $sourceSemester->id,
            'course_id' => $course->id,
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $set['batch']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'is_active' => true,
            'status' => 'draft',
        ]);

        $published = TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $set['program']->id,
            'term_id' => $targetTerm->id,
            'batch_id' => $set['batch']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $set['teacher']->id,
            'classroom_id' => $set['room']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $set['slot']->id,
            'is_active' => true,
            'status' => 'published',
        ]);

        $csv = implode("\n", [
            'day_of_week,timetable_slot_id,subject_id,teacher_id,classroom_id',
            "1,{$set['slot']->id},{$set['subject']->id},{$set['teacher']->id},{$set['room']->id}",
        ]);

        $importResult = app(TimetableImportService::class)->importCSV(
            UploadedFile::fake()->createWithContent('replace.csv', $csv),
            $set['program']->id,
            $targetTerm->id,
            $set['batch']->id,
            ['semester_id' => $semester->id, 'course_id' => $course->id]
        );

        $this->assertSame(0, $importResult['imported']);
        $this->assertNotEmpty($importResult['errors']);
        $this->assertDatabaseHas('timetable_entries', [
            'id' => $published->id,
            'status' => 'published',
            'subject_id' => $set['subject']->id,
        ]);

        $copyResult = app(TimetableCopyService::class)->executeCopy(
            $set['term']->id,
            $targetTerm->id,
            $set['program']->id,
            $set['batch']->id,
            [
                'replace_existing' => true,
                'semester_id' => $semester->id,
                'course_id' => $course->id,
            ]
        );

        $this->assertTrue($copyResult['success']);
        $this->assertSame(0, $copyResult['copied']);
        $this->assertNotEmpty($copyResult['errors']);
        $this->assertDatabaseHas('timetable_entries', [
            'id' => $published->id,
            'status' => 'published',
            'subject_id' => $set['subject']->id,
        ]);
    }
}
