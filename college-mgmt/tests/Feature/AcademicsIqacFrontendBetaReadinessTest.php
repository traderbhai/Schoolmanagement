<?php

namespace Tests\Feature;

use App\Models\CoAttainment;
use App\Models\CourseOutcome;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\FrontendNavigation;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsIqacFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_iqac_surfaces_open_without_debug_traces(): void
    {
        $iqac = User::where('email', 'iqac.head@college.com')->firstOrFail();

        $routes = [
            'academics.iqac.index',
            'academics.iqac.obe-readiness',
            'academics.iqac.attainment-monitoring',
            'academics.iqac.feedback-quality',
            'academics.iqac.audit-compliance',
            'academics.iqac.reports',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($iqac)->get(route($route));

            $response->assertOk()
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Stack trace', false)
                ->assertSee('page-body', false);
        }
    }

    public function test_iqac_section_filters_are_source_backed_and_metric_links_are_not_placeholders(): void
    {
        $iqac = User::where('email', 'iqac.head@college.com')->firstOrFail();
        $target = $this->createAttainmentFixture('CO-FRONTEND-IQAC-TARGET', 'Frontend IQAC Target Subject');
        $other = $this->createAttainmentFixture('CO-FRONTEND-IQAC-HIDDEN', 'Frontend IQAC Hidden Subject');

        $response = $this->actingAs($iqac)->get(route('academics.iqac.attainment-monitoring', [
            'search' => $target->code,
            'status' => 'CO target missed',
        ]));

        $response->assertOk()
            ->assertSee($target->code)
            ->assertDontSee($other->code)
            ->assertSee('Visible filter summary: Search: ' . $target->code . ' | Status: CO target missed')
            ->assertSee('Export current view')
            ->assertSee(e(route('academics.iqac.attainment-monitoring', [
                'metric' => 'co_target_missed',
                'program_id' => $target->subject->program_id,
                'term_id' => $target->attainment_term_id,
            ])), false)
            ->assertDontSee('href="#source-list"', false)
            ->assertDontSee('href="#"', false);

        $metricResponse = $this->actingAs($iqac)->get(route('academics.iqac.attainment-monitoring', [
            'metric' => 'co_target_missed',
            'search' => $target->code,
            'status' => 'CO target missed',
        ]));

        $metricResponse->assertOk()
            ->assertSee('Visible filter summary: Metric: co_target_missed | Search: ' . $target->code . ' | Status: CO target missed')
            ->assertSee('Reset queue')
            ->assertSee(route('academics.iqac.attainment-monitoring', ['metric' => 'co_target_missed']), false);

        $csv = $this->actingAs($iqac)->get(route('academics.iqac.attainment-monitoring', [
            'search' => $target->code,
            'status' => 'CO target missed',
            'export' => 'current',
        ]))->assertOk()->streamedContent();

        $this->assertStringContainsString($target->code, $csv);
        $this->assertStringNotContainsString($other->code, $csv);
    }

    public function test_primary_iqac_views_do_not_contain_placeholder_actions_or_broken_form_markup(): void
    {
        $viewPaths = [
            resource_path('views/academics/iqac/dashboard.blade.php'),
            resource_path('views/academics/iqac/section.blade.php'),
        ];

        foreach ($viewPaths as $path) {
            $contents = file_get_contents($path);

            $this->assertStringNotContainsString('href="#"', $contents, $path);
            $this->assertStringNotContainsString("href='#'", $contents, $path);
            $this->assertStringNotContainsString('href="#source-list"', $contents, $path);
            $this->assertStringNotContainsString('</form><form', $contents, $path);
            $this->assertStringNotContainsString('Â', $contents, $path);
        }
    }

    public function test_iqac_shared_shell_uses_manifest_grouped_sidebar_links(): void
    {
        $iqac = User::where('email', 'iqac.head@college.com')->firstOrFail();

        $response = $this->actingAs($iqac)->get(route('academics.iqac.index'));

        $response->assertOk()
            ->assertSee('IQAC OS')
            ->assertSee('IQAC Workspace')
            ->assertSee('Academics Governance')
            ->assertSee('OBE Framework')
            ->assertSee('Attainment')
            ->assertSee('Feedback Quality')
            ->assertSee(route('academics.iqac.obe-readiness'), false)
            ->assertDontSee(route('academic.obe.co.index'), false)
            ->assertSee(route('academics.workspaces.show', 'iqac'), false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);

        $iqacGroups = FrontendNavigation::manifest()['iqac']['groups'];

        $this->assertNotContains('Academics Governance', collect($iqacGroups['Command'])->pluck('label')->all());
        $this->assertContains('Academics Governance', collect($iqacGroups['Governance'])->pluck('label')->all());
        $this->assertNotContains('OBE Readiness', collect($iqacGroups['Command'])->pluck('label')->all());
    }

    public function test_iqac_manager_and_officer_rendered_navigation_links_are_reachable(): void
    {
        foreach (['iqac.manager@college.com', 'iqac.officer@college.com'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $html = $this->actingAs($user)->get(route('academics.iqac.index'))
                ->assertOk()
                ->assertDontSee('SERVICE ERROR')
                ->assertDontSee('Whoops')
                ->content();

            foreach ($this->internalGetLinks($html) as $path) {
                $status = $this->actingAs($user)->get($path)->getStatusCode();
                $this->assertNotContains($status, [403, 404, 500], "{$email} visible link {$path} returned a blocked/broken status.");
            }
        }
    }

    private function createAttainmentFixture(string $coCode, string $subjectName): CourseOutcome
    {
        $program = Program::where('code', 'PGDM')->first() ?? Program::query()->first();
        $department = $program?->department ?? Department::factory()->create(['code' => fake()->unique()->lexify('IQ??')]);

        if (! $program) {
            $program = Program::factory()->create([
                'department_id' => $department->id,
                'code' => fake()->unique()->lexify('PG??'),
                'is_active' => true,
            ]);
        }

        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'name' => $subjectName,
            'is_active' => true,
        ]);
        $term = Term::factory()->create(['program_id' => $program->id]);
        Semester::factory()->create(['is_current' => true]);
        Student::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);

        $co = CourseOutcome::create([
            'subject_id' => $subject->id,
            'code' => $coCode,
            'description' => 'Frontend IQAC attainment fixture.',
            'bloom_level' => 'analyze',
            'is_active' => true,
        ]);

        CoAttainment::create([
            'course_outcome_id' => $co->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'direct_attainment' => 2.1,
            'indirect_attainment' => 2.2,
            'final_attainment' => 2.15,
            'target_attainment' => 3.0,
            'target_met' => false,
        ]);

        $co->setRelation('subject', $subject);
        $co->attainment_term_id = $term->id;

        return $co;
    }

    private function internalGetLinks(string $html): array
    {
        preg_match_all('/href="([^"]+)"/', $html, $matches);

        return collect($matches[1] ?? [])
            ->reject(fn (string $href) => $href === '#' || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:'))
            ->map(function (string $href) {
                $parts = parse_url(html_entity_decode($href));
                if (! $parts || isset($parts['host']) && ! in_array($parts['host'], ['localhost', '127.0.0.1'], true)) {
                    return null;
                }

                $path = $parts['path'] ?? '/';
                if (preg_match('/\.(json|css|js|png|jpg|jpeg|svg|ico|webmanifest)$/', $path)) {
                    return null;
                }

                return $path . (isset($parts['query']) ? '?'.$parts['query'] : '');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
