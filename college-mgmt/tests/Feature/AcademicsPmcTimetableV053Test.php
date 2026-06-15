<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV053Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v053 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);
        TimetableSlot::firstOrCreate(['name' => 'Fixture Period 1'], ['start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 1, 'is_active' => true]);
        Classroom::firstOrCreate(['room_number' => 'FIX-101'], ['name' => 'Fixture Room', 'capacity' => 60, 'type' => 'lecture', 'is_active' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_timetable_forms_use_database_backed_selectors_instead_of_raw_ids(): void
    {
        $chair = $this->seedFixture();

        $pages = [
            route('academics.pmc.course-allocation.index') => ['Select program', 'Select student', 'Management Analytics'],
            route('academics.pmc.course-groups.index') => ['Select subject', 'Source group', 'PGDM Core Section A'],
            route('academics.pmc.section-faculty-allocation.index') => ['Select section/group', 'Select faculty', 'Prof. Aditi Sen'],
            route('academics.pmc.locked-slots.index') => ['Select day', 'Select time slot', 'No specific room'],
            route('academics.pmc.timetable-generator.index') => ['Any program', 'Any batch', 'Any term'],
            route('academics.pmc.substitution-intelligence.index') => ['Any course group', 'Select unavailable faculty'],
            route('academics.pmc.timetable-versions-v041.index') => ['Latest applicable version'],
            route('academics.pmc.official-timetable.index') => ['All programs', 'All terms'],
        ];

        foreach ($pages as $url => $expectedTexts) {
            $response = $this->actingAs($chair)->get($url)->assertOk();
            foreach ($expectedTexts as $text) {
                $response->assertSee($text);
            }
            foreach (['Program ID', 'Batch ID', 'Term ID', 'Subject ID', 'Teacher ID', 'Course group ID', 'Room ID', 'Timetable slot ID'] as $rawPrompt) {
                $response->assertDontSee($rawPrompt);
            }
        }
    }
}
