<?php

namespace Tests\Feature;

use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV048Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v048 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_refreshes_and_decides_room_readiness_reviews(): void
    {
        $chair = $this->seedFixture();
        $run = AcademicPmcTimetableGenerationRun::where('title', 'PMC v0.041 Balanced Draft')->firstOrFail();
        AcademicPmcRoomReadinessReview::query()->delete();

        $this->actingAs($chair)->post(route('academics.pmc.room-readiness-reviews.refresh'), [
            'generation_run_id' => $run->id,
        ])->assertRedirect();

        $review = AcademicPmcRoomReadinessReview::where('generation_run_id', $run->id)->firstOrFail();
        $this->assertContains($review->readiness_band, ['ready', 'warning', 'blocked']);
        $this->assertDatabaseHas('academic_pmc_timetable_publish_checks', [
            'generation_run_id' => $run->id,
            'check_type' => 'room_readiness',
        ]);

        $this->actingAs($chair)->patch(route('academics.pmc.room-readiness-reviews.decide', $review), [
            'status' => 'approved',
            'decision_note' => 'PMC verified room capacity and lab readiness.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_room_readiness_reviews', [
            'id' => $review->id,
            'status' => 'approved',
            'decision_note' => 'PMC verified room capacity and lab readiness.',
        ]);
    }

    public function test_blocked_room_readiness_requires_exception_or_revision_before_publish_check_passes(): void
    {
        $chair = $this->seedFixture();
        $run = AcademicPmcTimetableGenerationRun::where('title', 'PMC v0.041 Balanced Draft')->firstOrFail();
        $review = AcademicPmcRoomReadinessReview::where('generation_run_id', $run->id)->firstOrFail();
        $review->update(['readiness_band' => 'blocked', 'status' => 'review_required', 'risk_reasons' => ['capacity_below_largest_group']]);

        $this->actingAs($chair)->patch(route('academics.pmc.room-readiness-reviews.decide', $review), [
            'status' => 'approved_with_exception',
            'decision_note' => 'Dean approved temporary alternate seating plan.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_timetable_publish_checks', [
            'generation_run_id' => $run->id,
            'check_type' => 'room_readiness',
            'status' => 'pass',
        ]);
    }

    public function test_timetable_reports_show_room_readiness_reviews(): void
    {
        $chair = $this->seedFixture();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-reports.index'))
            ->assertOk()
            ->assertSee('Room And Lab Readiness Reviews')
            ->assertSee('Refresh Room Readiness')
            ->assertSee('PMC v041 Lecture Room');
    }
}
