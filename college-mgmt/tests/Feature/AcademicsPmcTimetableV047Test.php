<?php

namespace Tests\Feature;

use App\Models\AcademicPmcFacultyLoadReview;
use App\Models\AcademicPmcTimetableGenerationRun;
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

class AcademicsPmcTimetableV047Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v047 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_refreshes_and_decides_faculty_load_reviews(): void
    {
        $chair = $this->seedFixture();
        $run = AcademicPmcTimetableGenerationRun::where('title', 'PMC v0.041 Balanced Draft')->firstOrFail();
        AcademicPmcFacultyLoadReview::query()->delete();

        $this->actingAs($chair)->post(route('academics.pmc.faculty-load-reviews.refresh'), [
            'generation_run_id' => $run->id,
        ])->assertRedirect();

        $review = AcademicPmcFacultyLoadReview::where('generation_run_id', $run->id)->firstOrFail();
        $this->assertContains($review->load_band, ['normal', 'underload', 'overload', 'critical']);

        $this->actingAs($chair)->patch(route('academics.pmc.faculty-load-reviews.decide', $review), [
            'status' => $review->load_band === 'normal' ? 'approved' : 'approved_overload',
            'decision_note' => 'PMC reviewed load against availability and timetable.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_faculty_load_reviews', [
            'id' => $review->id,
            'decision_note' => 'PMC reviewed load against availability and timetable.',
        ]);
    }

    public function test_load_planning_page_shows_load_reviews(): void
    {
        $chair = $this->seedFixture();

        $this->actingAs($chair)
            ->get(route('academics.pmc.load-planning.index'))
            ->assertOk()
            ->assertSee('Faculty Load Reviews')
            ->assertSee('Refresh Load Reviews')
            ->assertSee('weekly_limit_exceeded');
    }
}
