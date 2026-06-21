<?php

namespace Tests\Feature;

use App\Models\Batch;
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
