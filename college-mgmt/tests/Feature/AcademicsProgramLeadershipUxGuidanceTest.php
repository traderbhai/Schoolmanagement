<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\Program;
use App\Models\ProgramSubject;
use App\Models\RoleProgramAssignment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsProgramLeadershipUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_program_leadership_dashboard_explains_operating_sequence(): void
    {
        $leader = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($leader)
            ->get(route('academics.program-leadership.index'))
            ->assertOk()
            ->assertSee('Program leadership operating sequence')
            ->assertSee('Each KPI opens the scoped source list behind the count')
            ->assertSee('Owner: assigned Program Leader / Director')
            ->assertSee('Source: portfolio, delivery, student success, quality signals, Chair escalation')
            ->assertSee('Owner / Source')
            ->assertSee('1. Review portfolio scope')
            ->assertSee('2. Clear course delivery gaps')
            ->assertSee('3. Triage student risk')
            ->assertSee('4. Check quality signals')
            ->assertSee('5. Escalate through Chair workflows')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_program_leadership_source_lists_explain_signal_to_action_workflow(): void
    {
        $leader = User::where('email', 'chair@college.com')->firstOrFail();

        foreach ([
            'academics.program-leadership.portfolio',
            'academics.program-leadership.course-delivery',
            'academics.program-leadership.student-success',
            'academics.program-leadership.quality-signals',
            'academics.program-leadership.reports',
        ] as $route) {
            $this->actingAs($leader)
                ->get(route($route))
                ->assertOk()
                ->assertSee('Program source-list workflow')
                ->assertSee('Owner: assigned Program Leader / Director')
                ->assertSee('Source:')
                ->assertSee('1. Filter program/status')
                ->assertSee('2. Review risk or blocker')
                ->assertSee('3. Open source workflow')
                ->assertSee('4. Assign or escalate action')
                ->assertSee('5. Export current view')
                ->assertSee('Visible filter summary')
                ->assertSee('Export current view')
                ->assertSee('Owner / Source')
                ->assertDontSee('href="#"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);
        }
    }

    public function test_program_leadership_empty_filtered_source_list_explains_scope_and_escalation_boundaries(): void
    {
        $leader = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($leader)
            ->get(route('academics.program-leadership.student-success', ['search' => 'no-matching-program-risk-record']))
            ->assertOk()
            ->assertSee('No program leadership records match this source list')
            ->assertSee('assigned program scope has no matching risks')
            ->assertSee('source workflows have not yet created portfolio, course-delivery, student-intervention, quality-signal, or Chair-escalation records')
            ->assertSee('recheck scope assignment, owner action, student-risk evidence, delivery progress, and escalation status')
            ->assertDontSee('No program leadership records match the current scope and filters')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_program_chair_timetable_publish_confirmation_explains_downstream_impact(): void
    {
        $contents = file_get_contents(resource_path('views/departmental/program-chair/timetable/builder.blade.php'));

        $this->assertStringContainsString('Run the conflict audit and publish this timetable for the selected program, batch, and term?', $contents);
        $this->assertStringContainsString('faculty, rooms, student timetables, attendance, and downstream reports', $contents);
        $this->assertStringNotContainsString("confirm('Run conflict audit and publish?')", $contents);
    }

    public function test_program_chair_faculty_assignment_removal_explains_timetable_and_workload_impact(): void
    {
        $contents = file_get_contents(resource_path('views/departmental/program-chair/curriculum/assignments.blade.php'));

        $this->assertStringContainsString('Confirm course group coverage, timetable sessions, workload approval, and student communication before unassigning faculty.', $contents);
        $this->assertStringContainsString('aria-label="Remove faculty assignment', $contents);
        $this->assertStringNotContainsString("confirm('Remove this faculty assignment?')", $contents);
    }

    public function test_program_chair_bulk_mentor_assignment_explains_student_support_impact(): void
    {
        $contents = file_get_contents(resource_path('views/departmental/program-chair/students/mentors.blade.php'));

        $this->assertStringContainsString('Confirm existing mentor coverage, student support ownership, and downstream mentor reports before bulk reassignment.', $contents);
        $this->assertStringNotContainsString("confirm('Assign mentor to entire batch?')", $contents);
    }

    public function test_program_chair_curriculum_subjects_with_downstream_usage_are_read_only(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $programId = RoleProgramAssignment::where('user_id', $chair->id)->where('is_active', true)->value('program_id')
            ?? Program::where('is_active', true)->value('id');
        $program = Program::findOrFail($programId);
        $term = Term::where('program_id', $program->id)->first()
            ?? Term::factory()->create(['program_id' => $program->id]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Locked Curriculum Analytics',
            'code' => 'LOCK-CURR',
        ]);
        $programSubject = ProgramSubject::create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'type' => 'compulsory',
            'credits' => 3,
            'is_active' => true,
        ]);

        AcademicPmcCourseGroup::create([
            'name' => 'Locked Curriculum Group',
            'group_type' => 'section',
            'program_id' => $program->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 12,
            'status' => 'active',
            'is_locked' => true,
        ]);

        $this->actingAs($chair)
            ->get(route('chair.curriculum.index', [
                'program_id' => $program->id,
                'term_id' => $term->id,
            ]))
            ->assertOk()
            ->assertSee('Locked Curriculum Analytics')
            ->assertSee('Locked')
            ->assertSee('Use curriculum revision')
            ->assertSee('course groups', false)
            ->assertDontSee(route('chair.curriculum.remove-subject', $programSubject), false);

        $this->actingAs($chair)
            ->delete(route('chair.curriculum.remove-subject', $programSubject))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('program_subjects', ['id' => $programSubject->id]);
    }
}
