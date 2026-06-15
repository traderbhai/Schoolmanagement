<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseDeliveryCheckpoint;
use App\Models\AcademicPmcRemedialAction;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcV058Test extends TestCase
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

    public function test_pmc_course_delivery_refresh_remedial_and_page_workflow(): void
    {
        $chair = $this->seedFixture();

        $this->assertTrue(AcademicPmcCourseDeliveryCheckpoint::where('risk_band', 'critical')->exists());
        $this->assertTrue(AcademicPmcRemedialAction::where('action_type', 'makeup_session')->exists());

        $this->actingAs($chair)
            ->post(route('academics.pmc.course-delivery.refresh'))
            ->assertRedirect();

        $checkpoint = AcademicPmcCourseDeliveryCheckpoint::whereNotNull('subject_id')->firstOrFail();
        $this->assertContains($checkpoint->risk_band, ['low', 'medium', 'high', 'critical']);
        $this->assertIsArray($checkpoint->signals);

        $this->actingAs($chair)
            ->post(route('academics.pmc.course-delivery.remedial-actions.store', $checkpoint), [
                'action_type' => 'delivery_review',
                'priority' => 'high',
                'reason' => 'Delivery checkpoint requires PMC remedial review.',
                'action_plan' => 'Review planned vs conducted sessions and collect faculty evidence.',
                'due_at' => now()->addDays(2)->toDateString(),
            ])
            ->assertRedirect();

        $action = AcademicPmcRemedialAction::where('checkpoint_id', $checkpoint->id)
            ->where('action_type', 'delivery_review')
            ->firstOrFail();
        $this->assertSame('open', $action->status);

        $this->actingAs($chair)
            ->patch(route('academics.pmc.remedial-planning.actions.update', $action), [
                'status' => 'resolved',
                'evidence_note' => 'Makeup class calendar and marks submission evidence attached.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_remedial_actions', [
            'id' => $action->id,
            'status' => 'resolved',
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.course-delivery.index'))
            ->assertOk()
            ->assertSee('PMC Course Delivery Checkpoints')
            ->assertSee('Refresh Delivery Signals')
            ->assertSee('Create Remedial')
            ->assertSee('Remedial Action Lifecycle');
    }
}
