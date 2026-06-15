<?php

namespace Tests\Feature;

use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Term;
use App\Models\TimetableVersion;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV082Test extends TestCase
{
    use RefreshDatabase;

    public function test_publish_freeze_readiness_renders_on_dashboard_and_version_page(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Publish And Freeze Readiness')
            ->assertSee('Version Status')
            ->assertSee('Open version lifecycle board');

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-versions-v041.index'))
            ->assertOk()
            ->assertSee('Publish And Freeze Readiness')
            ->assertSee('Missing Workflow')
            ->assertSee('Timetable Versions');
    }

    public function test_publish_freeze_blockers_are_counted_in_launch_control(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $department = Department::firstOrCreate(['code' => 'V082'], ['name' => 'v082 Department']);
        $program = Program::create([
            'department_id' => $department->id,
            'name' => 'v082 Program',
            'code' => 'V082-P',
            'system_type' => 'semester',
            'duration_years' => 2,
            'total_terms' => 4,
            'default_intake_capacity' => 60,
            'is_active' => true,
        ]);
        $batch = Batch::create([
            'program_id' => $program->id,
            'name' => 'v082 Batch',
            'code' => 'V082-B',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'intake_capacity' => 60,
            'status' => 'active',
        ]);
        $term = Term::create([
            'batch_id' => $batch->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'v082 Term',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'is_current' => true,
            'sort_order' => 1,
        ]);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 82,
            'status' => 'published',
            'created_by' => $chair->id,
            'published_by' => $chair->id,
            'published_at' => now(),
            'effective_from' => now()->toDateString(),
            'notes' => 'v082 fixture without lifecycle workflow',
        ]);

        AcademicPmcTimetablePublishCheck::create([
            'timetable_version_id' => $version->id,
            'check_type' => 'v082_publish_block',
            'status' => 'block',
            'severity' => 'high',
            'title' => 'v082 publish block',
            'description' => 'Fixture publish blocker.',
            'required_role' => 'dean_academics',
        ]);
        AcademicPmcTimetableChangeRequest::create([
            'timetable_version_id' => $version->id,
            'change_type' => 'revision',
            'status' => 'requested',
            'requested_by' => $chair->id,
            'reason' => 'v082 pending revision fixture',
        ]);
        AcademicPmcTimetableNotification::create([
            'notification_type' => 'publish',
            'recipient_type' => 'students',
            'title' => 'v082 failed notice',
            'message' => 'Fixture failed notification.',
            'status' => 'failed',
            'source_type' => 'timetable_version',
            'source_key' => (string) $version->id,
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Publish and notify')
            ->assertSee('Publish And Freeze Readiness')
            ->assertSee('Missing Workflow')
            ->assertSee('Publish Blocks')
            ->assertSee('Change Requests')
            ->assertSee('Failed Notices')
            ->assertSee('Clear official version, lifecycle workflow, publish-check, revision, and failed-notification blockers before final freeze.');
    }
}
