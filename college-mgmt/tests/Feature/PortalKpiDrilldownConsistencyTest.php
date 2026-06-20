<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalKpiDrilldownConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_student_pending_assignment_priority_opens_matching_filtered_source_list(): void
    {
        $user = User::where('email', 'arjun.k@demo.edu')->firstOrFail();
        $student = Student::where('user_id', $user->id)->firstOrFail();
        $subjectId = $student->subjects()->value('subjects.id');

        if ($subjectId) {
            Assignment::create([
                'subject_id' => $subjectId,
                'created_by' => User::where('email', 'anjali@demo.edu')->value('id') ?: $user->id,
                'term_id' => $student->current_term_id,
                'title' => 'KPI Drilldown Pending Assignment',
                'description' => 'Seeded by portal KPI drilldown consistency test.',
                'instructions' => 'Submit before the deadline.',
                'max_marks' => 20,
                'due_at' => now()->addDays(3),
                'allow_late_submission' => true,
                'late_penalty_percent' => 0,
                'is_published' => true,
            ]);
        }

        $expected = Assignment::with(['submissions' => fn ($query) => $query->where('student_id', $student->id)])
            ->whereIn('subject_id', $student->subjects()->pluck('subjects.id'))
            ->where('is_published', true)
            ->where('due_at', '>', now())
            ->where('due_at', '<=', now()->addDays(7))
            ->get()
            ->filter(function ($assignment) {
                $submission = $assignment->submissions->first();

                return ! $submission || ! in_array($submission->status, ['submitted', 'graded'], true);
            })
            ->count();

        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(route('student.assignments.index', ['filter' => 'pending_next_7']), false);

        $this->actingAs($user)
            ->get(route('student.assignments.index', ['filter' => 'pending_next_7']))
            ->assertOk()
            ->assertSee('Visible filter summary: Pending assignments due in the next 7 days')
            ->assertSee('Filtered Source List (' . $expected . ')');
    }

    public function test_portal_dashboards_do_not_expose_fake_metric_links(): void
    {
        foreach ([
            'anjali@demo.edu' => 'teacher.dashboard',
            'parent@demo.edu' => 'parent.dashboard',
            'priya.sharma@applicant.demo' => 'applicant.dashboard',
        ] as $email => $route) {
            $this->actingAs(User::where('email', $email)->firstOrFail())
                ->get(route($route))
                ->assertOk()
                ->assertDontSee('href="#"', false);
        }
    }
}
