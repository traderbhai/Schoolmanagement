<?php

namespace Tests\Feature;

use App\Models\AcademicPmcDataReconciliationCheck;
use App\Models\AcademicPmcDataReconciliationRun;
use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\AcademicPmcTimetableV041Service;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AcademicsPmcTimetableV092Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v092 Student', 'email' => 'pmc.v092.student@example.test']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return ['chair' => User::where('email', 'chair@college.com')->firstOrFail()];
    }

    public function test_pmc_data_reconciliation_page_renders_seeded_checks(): void
    {
        $fixture = $this->seedFixture();
        $actorUser = User::factory()->create(['name' => 'Audit Filter User', 'email' => 'audit.filter.user@example.test']);
        $department = Department::where('code', 'ACAD')->firstOrFail();
        DepartmentActivityLog::create([
            'department_id' => $department->id,
            'actor_user_id' => $actorUser->id,
            'action' => 'academic_pmc_v105_reconciliation_stale_run_closed',
            'subject_type' => AcademicPmcDataReconciliationRun::class,
            'subject_id' => AcademicPmcDataReconciliationRun::first()?->id,
            'description' => 'Actor scoped audit entry for filtering test.',
            'metadata' => ['reason' => 'Audit actor filter check', 'version' => 'PMC OS v0.111'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.index'))
            ->assertOk()
            ->assertSee('PMC Data Reconciliation')
            ->assertSee('Scheduled Run History')
            ->assertSee('Scheduler Health')
            ->assertSee('Attention Needed')
            ->assertSee('Review stale running reconciliation jobs')
            ->assertSee('Run Total')
            ->assertSee('Failed')
            ->assertSee('Run filter summary: All run history')
            ->assertSee('Demo Seed')
            ->assertSee('Demo failed run showing scheduler failure visibility.')
            ->assertSee('Reconciliation Checks')
            ->assertSee('Recent Reconciliation Audit Trail')
            ->assertSee('Demo reconciliation refresh audit for seeded PMC data health.')
            ->assertSee('Approved allocations linked to student subject enrollments');

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.index', ['run_status' => 'failed']))
            ->assertOk()
            ->assertSee('Run filter summary: Run status=failed')
            ->assertSee('Demo failed run showing scheduler failure visibility.')
            ->assertSee('Demo reconciliation repair audit for allocation enrollment links.');

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.index', ['run_status' => 'running']))
            ->assertOk()
            ->assertSee('Run filter summary: Run status=running')
            ->assertSee('Demo stale running run for scheduler health warning.')
            ->assertDontSee('Demo failed run showing scheduler failure visibility.');

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.index', [
                'audit_action' => 'academic_pmc_v093_data_reconciliation_repaired',
            ]))
            ->assertOk()
            ->assertSee('Audit filter summary: Action=Academic Pmc V093 Data Reconciliation Repaired')
            ->assertSee('Demo reconciliation repair audit for allocation enrollment links.')
            ->assertDontSee('Demo reconciliation refresh audit for seeded PMC data health.');

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.index', ['audit_actor_id' => $actorUser->id]))
            ->assertOk()
            ->assertSee('Actor=' . $actorUser->name)
            ->assertSee('Actor scoped audit entry for filtering test.')
            ->assertDontSee('Demo reconciliation refresh audit for seeded PMC data health.');

        $response = $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.audit.export', [
                'audit_actor_id' => $actorUser->id,
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Actor scoped audit entry for filtering test.', $response->streamedContent());

        $export = AcademicPmcExportLog::where('report_key', 'data_reconciliation_audit')->latest()->firstOrFail();
        $this->assertSame((string) $actorUser->id, (string) ($export->filters['actor_user_id'] ?? ''));
    }

    public function test_refresh_creates_reconciliation_checks(): void
    {
        $fixture = $this->seedFixture();
        AcademicPmcDataReconciliationCheck::query()->delete();

        $this->actingAs($fixture['chair'])
            ->post(route('academics.pmc.data-reconciliation.refresh'))
            ->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_data_reconciliation_checks', [
            'check_key' => 'generated_operational_sync',
            'check_group' => 'timetable',
        ]);
        $this->assertDatabaseHas('academic_pmc_data_reconciliation_checks', [
            'check_key' => 'allocation_enrollment_links',
            'check_group' => 'course_basket',
        ]);
        $this->assertDatabaseHas('academic_pmc_data_reconciliation_runs', [
            'source' => 'manual_ui_refresh',
            'status' => 'completed',
            'repair_requested' => false,
            'started_by' => $fixture['chair']->id,
        ]);
    }

    public function test_reconciliation_detects_unsynced_published_generation_items(): void
    {
        $fixture = $this->seedFixture();
        $run = AcademicPmcTimetableGenerationRun::whereNotNull('timetable_version_id')->firstOrFail();
        AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->whereIn('status', ['scheduled', 'published', 'locked'])->update(['operational_timetable_entry_id' => null]);

        $this->actingAs($fixture['chair'])
            ->post(route('academics.pmc.data-reconciliation.refresh'))
            ->assertRedirect();

        $check = AcademicPmcDataReconciliationCheck::where('check_key', 'generated_operational_sync')->firstOrFail();
        $this->assertGreaterThan(0, $check->mismatch_count);
        $this->assertContains($check->status, ['warn', 'block']);
        $this->assertNotEmpty($check->details['sample_mismatches'] ?? []);

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.index', ['group' => 'timetable']))
            ->assertOk()
            ->assertSee('Sample mismatches');
    }

    public function test_repair_links_allocations_to_student_subject_enrollments(): void
    {
        $fixture = $this->seedFixture();
        $allocation = AcademicPmcStudentCourseAllocation::where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->whereNotNull('student_id')
            ->whereNotNull('subject_id')
            ->firstOrFail();
        $allocation->update(['student_subject_enrollment_id' => null]);
        StudentSubjectEnrollment::where('student_id', $allocation->student_id)
            ->where('subject_id', $allocation->subject_id)
            ->where('term_id', $allocation->term_id)
            ->delete();

        $this->actingAs($fixture['chair'])
            ->post(route('academics.pmc.data-reconciliation.refresh'))
            ->assertRedirect();

        $check = AcademicPmcDataReconciliationCheck::where('check_key', 'allocation_enrollment_links')->firstOrFail();
        $this->assertGreaterThan(0, $check->mismatch_count);

        $this->actingAs($fixture['chair'])
            ->post(route('academics.pmc.data-reconciliation.repair', $check))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($allocation->fresh()->student_subject_enrollment_id);
        $this->assertDatabaseHas('academic_pmc_data_reconciliation_checks', [
            'check_key' => 'allocation_enrollment_links',
            'mismatch_count' => 0,
            'status' => 'ok',
        ]);
        $run = AcademicPmcDataReconciliationRun::where('source', 'manual_ui_repair')->latest('started_at')->firstOrFail();
        $this->assertTrue($run->repair_requested);
        $this->assertSame($fixture['chair']->id, $run->started_by);
        $this->assertGreaterThan(0, $run->repaired_count);
        $this->assertSame('allocation_enrollment_links', $run->metadata['check_key'] ?? null);
    }

    public function test_reconciliation_export_respects_filters_and_writes_export_log(): void
    {
        $fixture = $this->seedFixture();

        $this->actingAs($fixture['chair'])
            ->post(route('academics.pmc.data-reconciliation.refresh'))
            ->assertRedirect();

        $response = $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.export', ['group' => 'timetable']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Published generated classes synced to operational timetable', $response->streamedContent());
        $this->assertDatabaseHas('academic_pmc_export_logs', [
            'report_key' => 'data_reconciliation',
            'user_id' => $fixture['chair']->id,
        ]);

        $log = AcademicPmcExportLog::where('report_key', 'data_reconciliation')->latest()->firstOrFail();
        $this->assertSame('timetable', $log->filters['group'] ?? null);
        $this->assertGreaterThan(0, $log->row_count);
    }

    public function test_reconciliation_run_history_export_respects_status_and_writes_export_log(): void
    {
        $fixture = $this->seedFixture();

        $response = $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.runs.export', ['run_status' => 'failed']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Demo failed run showing scheduler failure visibility.', $csv);
        $this->assertStringNotContainsString('Hourly scheduler refreshed PMC reconciliation checks.', $csv);

        $log = AcademicPmcExportLog::where('report_key', 'data_reconciliation_runs')->latest()->firstOrFail();
        $this->assertSame('failed', $log->filters['run_status'] ?? null);
        $this->assertGreaterThan(0, $log->row_count);
    }

    public function test_stale_running_reconciliation_run_can_be_marked_failed(): void
    {
        $fixture = $this->seedFixture();
        $run = AcademicPmcDataReconciliationRun::where('status', 'running')->firstOrFail();

        $this->actingAs($fixture['chair'])
            ->patch(route('academics.pmc.data-reconciliation.runs.mark-failed', $run), [
                'reason' => 'Scheduler process confirmed stopped.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertSame('Scheduler process confirmed stopped.', $run->failure_reason);
        $this->assertSame($fixture['chair']->id, $run->metadata['manual_close']['closed_by'] ?? null);
        $this->assertDatabaseHas('department_activity_logs', [
            'action' => 'academic_pmc_v105_reconciliation_stale_run_closed',
            'subject_type' => AcademicPmcDataReconciliationRun::class,
            'subject_id' => $run->id,
            'actor_user_id' => $fixture['chair']->id,
        ]);
        $log = DepartmentActivityLog::where('action', 'academic_pmc_v105_reconciliation_stale_run_closed')->latest()->firstOrFail();
        $this->assertSame('Scheduler process confirmed stopped.', $log->metadata['reason'] ?? null);

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.index'))
            ->assertOk()
            ->assertSee('Recent Reconciliation Audit Trail')
            ->assertSee('Audit filter summary: All actions')
            ->assertSee('Export Audit Trail')
            ->assertSee('Scheduler process confirmed stopped.')
            ->assertSee('Academic Pmc V105 Reconciliation Stale Run Closed');

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.index', [
                'audit_action' => 'academic_pmc_v105_reconciliation_stale_run_closed',
            ]))
            ->assertOk()
            ->assertSee('Audit filter summary: Action=Academic Pmc V105 Reconciliation Stale Run Closed')
            ->assertSee('Scheduler process confirmed stopped.');

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.index', [
                'audit_to' => now()->subDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Audit filter summary: All actions; To=' . now()->subDay()->toDateString())
            ->assertSee('No reconciliation audit activity has been recorded yet.')
            ->assertDontSee('Demo stale scheduler run was closed after process check.');

        $response = $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.data-reconciliation.audit.export', [
                'audit_action' => 'academic_pmc_v105_reconciliation_stale_run_closed',
                'audit_from' => now()->toDateString(),
                'audit_to' => now()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('academic_pmc_v105_reconciliation_stale_run_closed', $csv);
        $this->assertStringContainsString('Scheduler process confirmed stopped.', $csv);

        $export = AcademicPmcExportLog::where('report_key', 'data_reconciliation_audit')->latest()->firstOrFail();
        $this->assertSame('academic_pmc_v105_reconciliation_stale_run_closed', $export->filters['action'] ?? null);
        $this->assertSame(now()->toDateString(), $export->filters['from'] ?? null);
        $this->assertSame(now()->toDateString(), $export->filters['to'] ?? null);
        $this->assertGreaterThan(0, $export->row_count);
    }

    public function test_fresh_running_reconciliation_run_cannot_be_marked_failed(): void
    {
        $fixture = $this->seedFixture();
        $run = AcademicPmcDataReconciliationRun::create([
            'source' => 'manual_ui_refresh',
            'status' => 'running',
            'repair_requested' => false,
            'started_by' => $fixture['chair']->id,
            'started_at' => now(),
        ]);

        $this->actingAs($fixture['chair'])
            ->patch(route('academics.pmc.data-reconciliation.runs.mark-failed', $run), [
                'reason' => 'Too early to close.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('running', $run->fresh()->status);
    }

    public function test_reconciliation_command_refreshes_checks(): void
    {
        $this->seedFixture();
        AcademicPmcDataReconciliationCheck::query()->delete();

        $this->artisan('academics:pmc-reconcile-data')
            ->expectsOutputToContain('PMC reconciliation refreshed')
            ->assertExitCode(0);

        $this->assertDatabaseHas('academic_pmc_data_reconciliation_checks', [
            'check_key' => 'generated_operational_sync',
        ]);
        $this->assertDatabaseHas('academic_pmc_data_reconciliation_runs', [
            'source' => 'scheduled_cli',
            'status' => 'completed',
            'repair_requested' => false,
        ]);
    }

    public function test_failed_manual_refresh_writes_failed_run_history(): void
    {
        $fixture = $this->seedFixture();
        $this->mock(AcademicPmcTimetableV041Service::class, function ($mock) {
            $mock->shouldReceive('refreshDataReconciliation')
                ->once()
                ->andThrow(new RuntimeException('Simulated reconciliation outage.'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($fixture['chair'])
                ->post(route('academics.pmc.data-reconciliation.refresh'));
            $this->fail('Expected reconciliation outage exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated reconciliation outage.', $exception->getMessage());
        }

        $run = AcademicPmcDataReconciliationRun::where('source', 'manual_ui_refresh')->latest('started_at')->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertSame($fixture['chair']->id, $run->started_by);
        $this->assertSame('Simulated reconciliation outage.', $run->failure_reason);
        $this->assertSame(RuntimeException::class, $run->metadata['exception'] ?? null);
    }

    public function test_reconciliation_command_repair_option_repairs_safe_drift(): void
    {
        $this->seedFixture();
        $allocation = AcademicPmcStudentCourseAllocation::where('waitlisted', false)
            ->whereIn('basket_status', ['allocated', 'approved', 'locked'])
            ->whereNotNull('student_id')
            ->whereNotNull('subject_id')
            ->firstOrFail();
        $allocation->update(['student_subject_enrollment_id' => null]);
        StudentSubjectEnrollment::where('student_id', $allocation->student_id)
            ->where('subject_id', $allocation->subject_id)
            ->where('term_id', $allocation->term_id)
            ->delete();

        $this->artisan('academics:pmc-reconcile-data --repair')
            ->expectsOutputToContain('PMC reconciliation repair completed')
            ->assertExitCode(0);

        $this->assertNotNull($allocation->fresh()->student_subject_enrollment_id);
        $run = AcademicPmcDataReconciliationRun::latest('started_at')->firstOrFail();
        $this->assertTrue($run->repair_requested);
        $this->assertSame('completed', $run->status);
        $this->assertGreaterThan(0, $run->repaired_count);
    }
}
