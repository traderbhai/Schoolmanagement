<?php

namespace Tests\Feature;

use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableVersion;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV043Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v043 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return [
            'chair' => User::where('email', 'chair@college.com')->firstOrFail(),
            'dean' => User::where('email', 'dean@college.com')->firstOrFail(),
            'run' => \App\Models\AcademicPmcTimetableGenerationRun::where('title', 'PMC v0.041 Balanced Draft')->firstOrFail(),
            'version' => TimetableVersion::where('version_number', 41)->firstOrFail(),
        ];
    }

    public function test_pmc_publish_is_blocked_when_hard_publish_checks_exist(): void
    {
        $fixture = $this->seedFixture();

        $this->actingAs($fixture['chair'])->post(route('academics.pmc.timetable-generator.publish', $fixture['run']), [
            'decision_reason' => 'Try publish with conflicts.',
        ])->assertStatus(422);
    }

    public function test_dean_can_override_publish_and_manage_freeze_lifecycle(): void
    {
        $fixture = $this->seedFixture();
        $dean = $fixture['dean'];
        $run = $fixture['run'];

        $this->actingAs($dean)->post(route('academics.pmc.timetable-generator.publish', $run), [
            'decision_reason' => 'Publish after leadership review.',
            'override_reason' => 'Hard conflict accepted for emergency demo timetable.',
            'effective_from' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $published = TimetableVersion::where('version_number', '>', 41)->latest('id')->firstOrFail();
        $this->assertDatabaseHas('academic_pmc_timetable_version_workflows', [
            'timetable_version_id' => $published->id,
            'approval_status' => 'dean_override_published',
        ]);
        $this->assertTrue(AcademicPmcTimetableNotification::where('source_type', 'timetable_version')->where('source_key', (string) $published->id)->exists());

        $this->actingAs($dean)->post(route('academics.pmc.timetable-versions-v041.freeze', $published), [
            'decision_reason' => 'Freeze after student/faculty notice.',
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_timetable_version_workflows', ['timetable_version_id' => $published->id, 'lifecycle_status' => 'frozen']);

        $this->actingAs($dean)->post(route('academics.pmc.timetable-versions-v041.unfreeze', $published), [
            'decision_reason' => 'Room capacity issue reopened.',
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_timetable_version_workflows', ['timetable_version_id' => $published->id, 'lifecycle_status' => 'revision_requested']);

        $entry = TimetableEntry::factory()->create([
            'semester_id' => Semester::factory()->create()->id,
            'course_id' => Course::factory()->create()->id,
            'timetable_version_id' => $published->id,
            'status' => 'published',
            'is_active' => true,
            'day_of_week' => 1,
        ]);

        $this->actingAs($dean)->post(route('academics.pmc.timetable-versions-v041.rollback', $published), [
            'decision_reason' => 'Rollback to prior working version.',
        ])->assertRedirect();
        $rollbackWorkflow = AcademicPmcTimetableVersionWorkflow::where('rollback_from_version_id', $published->id)
            ->where('approval_status', 'dean_rollback')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame($rollbackWorkflow->timetable_version_id, $entry->fresh()->timetable_version_id);
        $this->assertDatabaseHas('timetable_versions', [
            'id' => $rollbackWorkflow->timetable_version_id,
            'status' => 'published',
        ]);
    }

    public function test_publishing_new_version_retires_superseded_operational_entries(): void
    {
        $fixture = $this->seedFixture();
        $dean = $fixture['dean'];
        $run = $fixture['run'];
        $oldVersion = $fixture['version'];

        $oldEntry = TimetableEntry::factory()->create([
            'semester_id' => Semester::factory()->create()->id,
            'course_id' => Course::factory()->create()->id,
            'timetable_version_id' => $oldVersion->id,
            'status' => 'published',
            'is_active' => true,
            'day_of_week' => 1,
        ]);

        $this->actingAs($dean)->post(route('academics.pmc.timetable-generator.publish', $run), [
            'decision_reason' => 'Publish revised official timetable.',
            'override_reason' => 'Leadership approved conflicts for this readiness check.',
            'effective_from' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $oldEntry->refresh();
        $this->assertSame('archived', $oldEntry->status);
        $this->assertFalse((bool) $oldEntry->is_active);
        $this->assertSame('archived', $oldVersion->fresh()->status);
    }

    public function test_draft_timetable_version_cannot_be_rollback_source(): void
    {
        $fixture = $this->seedFixture();
        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['version']->program_id,
            'term_id' => $fixture['version']->term_id,
            'batch_id' => $fixture['version']->batch_id,
            'version_number' => 999,
            'status' => 'draft',
            'created_by' => $fixture['dean']->id,
            'notes' => 'Draft-only version must not become official rollback source.',
        ]);

        $this->actingAs($fixture['dean'])->post(route('academics.pmc.timetable-versions-v041.rollback', $draftVersion), [
            'decision_reason' => 'Attempt rollback from draft.',
        ])->assertStatus(422);

        $this->assertSame('draft', $draftVersion->fresh()->status);
    }
}
