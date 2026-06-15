<?php

namespace Tests\Feature;

use App\Models\AcademicPmcGroupDeliveryTracker;
use App\Models\AcademicPmcSessionDeliveryLog;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcV059Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_group_delivery_trackers_and_session_logs_are_refreshed_and_rendered(): void
    {
        $chair = $this->seedFixture();

        $this->assertTrue(AcademicPmcGroupDeliveryTracker::whereIn('risk_band', ['critical', 'high'])->exists());
        $this->assertTrue(AcademicPmcSessionDeliveryLog::where('session_status', 'missed')->exists());

        $this->actingAs($chair)
            ->post(route('academics.pmc.course-delivery.refresh'))
            ->assertRedirect();

        $tracker = AcademicPmcGroupDeliveryTracker::whereNotNull('course_group_id')->firstOrFail();
        $this->assertGreaterThanOrEqual(0, $tracker->planned_sessions);
        $this->assertContains($tracker->risk_band, ['low', 'medium', 'high', 'critical']);
        $this->assertIsArray($tracker->recommended_actions);

        $this->assertTrue(AcademicPmcSessionDeliveryLog::where('course_group_id', $tracker->course_group_id)->exists());

        $this->actingAs($chair)
            ->get(route('academics.pmc.course-delivery.index'))
            ->assertOk()
            ->assertSee('Section / Group Delivery Tracker')
            ->assertSee('Session Delivery Log Queue')
            ->assertSee('Pending logs')
            ->assertSee('Lesson plan');
    }
}
