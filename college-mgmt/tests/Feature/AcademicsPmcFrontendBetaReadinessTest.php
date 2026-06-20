<?php

namespace Tests\Feature;

use App\Models\AcademicPmcOperatingRecord;
use App\Models\AcademicPmcAnalyticsSnapshot;
use App\Models\AcademicPmcApproval;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\FeeDemand;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_pmc_operating_surfaces_open_without_debug_traces(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        foreach ([
            'academics.pmc.command',
            'academics.pmc.planning.index',
            'academics.pmc.curriculum-governance.index',
            'academics.pmc.faculty-allocation-v004.index',
            'academics.pmc.timetable-governance.index',
            'academics.pmc.course-delivery.index',
            'academics.pmc.student-success-v004.index',
            'academics.pmc.approvals.index',
            'academics.pmc.analytics.index',
            'academics.pmc.course-allocation.index',
            'academics.pmc.course-groups.index',
            'academics.pmc.timetable-planner.index',
        ] as $routeName) {
            $this->actingAs($chair)
                ->get(route($routeName))
                ->assertOk()
                ->assertDontSee('SERVICE ERROR')
                ->assertDontSee('Whoops')
                ->assertDontSee('Laravel')
                ->assertSee('<title', false);
        }
    }

    public function test_pmc_command_and_surfaces_have_source_linked_filters_and_exports(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $program = Program::where('is_active', true)->firstOrFail();

        AcademicPmcOperatingRecord::create([
            'record_type' => 'faculty_allocation',
            'title' => 'ZZZ Beta PMC Critical Faculty Load',
            'description' => 'Beta PMC source-backed filter test.',
            'program_id' => $program->id,
            'status' => 'open',
            'category' => 'beta_category',
            'risk_band' => 'critical',
            'score' => 91,
            'due_at' => now()->subDay(),
            'created_by' => $chair->id,
            'owner_user_id' => $chair->id,
        ]);

        AcademicPmcOperatingRecord::create([
            'record_type' => 'faculty_allocation',
            'title' => 'AAA Beta PMC Closed Faculty Load',
            'description' => 'Should be filtered out.',
            'program_id' => $program->id,
            'status' => 'closed',
            'category' => 'beta_category',
            'risk_band' => 'low',
            'score' => 5,
            'due_at' => now()->addWeek(),
            'created_by' => $chair->id,
            'owner_user_id' => $chair->id,
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.command'))
            ->assertOk()
            ->assertSee(route('academics.pmc.faculty-allocation-v004.index', ['risk_band' => 'high']), false)
            ->assertSee(route('academics.pmc.action-governance.index', ['due' => 'overdue']), false);

        $response = $this->actingAs($chair)->get(route('academics.pmc.faculty-allocation-v004.index', [
            'search' => 'Beta PMC',
            'risk_band' => 'critical',
            'status' => 'open',
            'due' => 'overdue',
        ]));

        $response
            ->assertOk()
            ->assertSee('ZZZ Beta PMC Critical Faculty Load')
            ->assertDontSee('AAA Beta PMC Closed Faculty Load')
            ->assertSee('Visible filter summary')
            ->assertSee('Search: Beta PMC')
            ->assertSee('Due: overdue')
            ->assertSee(e(route('academics.pmc.export', [
                'report' => 'faculty-allocation-v004',
                'search' => 'Beta PMC',
                'risk_band' => 'critical',
                'status' => 'open',
                'due' => 'overdue',
            ])), false);
    }

    public function test_pmc_approvals_and_analytics_use_matching_filtered_exports(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $program = Program::where('is_active', true)->firstOrFail();

        AcademicPmcApproval::create([
            'approval_type' => 'timetable_freeze',
            'title' => 'ZZZ Beta PMC Approval Export',
            'description' => 'Filtered approval export source.',
            'program_id' => $program->id,
            'requested_by' => $chair->id,
            'owner_user_id' => $chair->id,
            'status' => 'pending',
            'sla_status' => 'overdue',
            'due_at' => now()->subDay(),
            'source_type' => 'test',
            'source_key' => 'pmc-approval-export',
        ]);

        AcademicPmcApproval::create([
            'approval_type' => 'curriculum_change',
            'title' => 'AAA Beta PMC Approval Hidden',
            'description' => 'Should not appear in filtered approval export.',
            'program_id' => $program->id,
            'requested_by' => $chair->id,
            'owner_user_id' => $chair->id,
            'status' => 'approved',
            'sla_status' => 'on_track',
            'due_at' => now()->addWeek(),
            'source_type' => 'test',
            'source_key' => 'pmc-approval-hidden',
        ]);

        $approvalPage = $this->actingAs($chair)->get(route('academics.pmc.approvals.index', [
            'search' => 'Beta PMC Approval',
            'status' => 'pending',
            'due' => 'overdue',
            'sort' => 'title',
            'direction' => 'desc',
        ]));

        $approvalPage->assertOk()
            ->assertSee('ZZZ Beta PMC Approval Export')
            ->assertDontSee('AAA Beta PMC Approval Hidden')
            ->assertSee('Visible filter summary')
            ->assertSee('Search: Beta PMC Approval')
            ->assertSee(e(route('academics.pmc.export', [
                'report' => 'approvals',
                'search' => 'Beta PMC Approval',
                'status' => 'pending',
                'due' => 'overdue',
                'sort' => 'title',
                'direction' => 'desc',
            ])), false);

        $approvalExport = $this->actingAs($chair)->get(route('academics.pmc.export', [
            'report' => 'approvals',
            'search' => 'Beta PMC Approval',
            'status' => 'pending',
            'due' => 'overdue',
            'sort' => 'title',
            'direction' => 'desc',
        ]));

        $approvalCsv = $approvalExport->streamedContent();
        $this->assertStringContainsString('ZZZ Beta PMC Approval Export', $approvalCsv);
        $this->assertStringNotContainsString('AAA Beta PMC Approval Hidden', $approvalCsv);
        $this->assertSame(1, AcademicPmcExportLog::where('report_key', 'approvals')->latest('id')->firstOrFail()->row_count);

        AcademicPmcAnalyticsSnapshot::create([
            'snapshot_type' => 'beta_pmc_risk_export',
            'program_id' => $program->id,
            'snapshot_date' => now()->toDateString(),
            'band' => 'critical',
            'score' => 88,
            'metrics' => ['source' => 'filtered'],
        ]);

        AcademicPmcAnalyticsSnapshot::create([
            'snapshot_type' => 'beta_pmc_risk_hidden',
            'program_id' => $program->id,
            'snapshot_date' => now()->subWeek()->toDateString(),
            'band' => 'low',
            'score' => 12,
            'metrics' => ['source' => 'hidden'],
        ]);

        $analyticsPage = $this->actingAs($chair)->get(route('academics.pmc.analytics.index', [
            'search' => 'beta_pmc_risk',
            'band' => 'critical',
            'sort' => 'score',
            'direction' => 'desc',
        ]));

        $analyticsPage->assertOk()
            ->assertSee('Beta Pmc Risk Export')
            ->assertDontSee('Beta Pmc Risk Hidden')
            ->assertSee('Search: beta_pmc_risk')
            ->assertSee('Band: critical')
            ->assertSee(e(route('academics.pmc.export', [
                'report' => 'analytics',
                'search' => 'beta_pmc_risk',
                'band' => 'critical',
                'sort' => 'score',
                'direction' => 'desc',
            ])), false);

        $analyticsExport = $this->actingAs($chair)->get(route('academics.pmc.export', [
            'report' => 'analytics',
            'search' => 'beta_pmc_risk',
            'band' => 'critical',
            'sort' => 'score',
            'direction' => 'desc',
        ]));

        $analyticsCsv = $analyticsExport->streamedContent();
        $this->assertStringContainsString('beta_pmc_risk_export', $analyticsCsv);
        $this->assertStringNotContainsString('beta_pmc_risk_hidden', $analyticsCsv);
        $this->assertSame(1, AcademicPmcExportLog::where('report_key', 'analytics')->latest('id')->firstOrFail()->row_count);
    }

    public function test_pmc_v041_allocation_group_and_planner_surfaces_export_filtered_source_rows(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $program = Program::where('is_active', true)->firstOrFail();
        $batch = Batch::where('program_id', $program->id)->firstOrFail();
        $term = Term::where('program_id', $program->id)->where('batch_id', $batch->id)->firstOrFail();
        $course = Course::where('department_id', $program->department_id)->firstOrFail();
        $departmentId = $program->department_id;

        $studentUser = User::create([
            'name' => 'ZZZ Beta PMC Allocation Student',
            'email' => 'beta.pmc.allocation.student@example.test',
            'password' => 'password',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'department_id' => $departmentId,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'enrollment_number' => 'BETA-PMC-ALLOC-001',
            'roll_number' => 'BPA001',
            'status' => 'active',
        ]);
        $subject = Subject::create([
            'department_id' => $departmentId,
            'program_id' => $program->id,
            'term_number' => $term->term_number,
            'name' => 'ZZZ Beta PMC Allocation Subject',
            'code' => 'BPA101',
            'credits' => 3,
            'type' => 'core',
            'hours_per_week' => 3,
            'is_active' => true,
        ]);

        AcademicPmcStudentCourseAllocation::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'allocation_type' => 'core',
            'allocation_source' => 'manual',
            'approval_status' => 'approved',
            'basket_status' => 'allocated',
            'priority_rank' => 1,
            'waitlisted' => false,
        ]);

        $group = AcademicPmcCourseGroup::create([
            'name' => 'ZZZ Beta PMC Export Group',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 1,
            'status' => 'ready',
            'is_locked' => true,
        ]);

        $teacher = Teacher::firstOrFail();
        $room = Classroom::firstOrCreate(['room_number' => 'BETA-PMC-101'], ['name' => 'Beta PMC Room', 'capacity' => 60, 'type' => 'lecture', 'is_active' => true]);
        $slot = TimetableSlot::firstOrCreate(['name' => 'Beta PMC Period 1'], ['start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 1, 'is_active' => true]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'ZZZ Beta PMC Generation Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'created_by' => $chair->id,
            'status' => 'generated',
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $group->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 2,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'is_locked' => true,
            'confidence' => 94,
            'explanation' => 'Filtered planner export fixture.',
        ]);

        $allocationPage = $this->actingAs($chair)->get(route('academics.pmc.course-allocation.index', [
            'search' => 'Beta PMC Allocation',
            'status' => 'allocated',
        ]));
        $allocationPage->assertOk()
            ->assertSee('ZZZ Beta PMC Allocation Student')
            ->assertSee('Visible filter summary')
            ->assertSee('Export Current View')
            ->assertSee(e(route('academics.pmc.v041.surface.export', [
                'surface' => 'course-allocation',
                'search' => 'Beta PMC Allocation',
                'status' => 'allocated',
            ])), false);

        $allocationCsv = $this->actingAs($chair)->get(route('academics.pmc.v041.surface.export', [
            'surface' => 'course-allocation',
            'search' => 'Beta PMC Allocation',
            'status' => 'allocated',
        ]))->streamedContent();
        $this->assertStringContainsString('ZZZ Beta PMC Allocation Student', $allocationCsv);

        $groupPage = $this->actingAs($chair)->get(route('academics.pmc.course-groups.index', [
            'search' => 'Beta PMC Export Group',
            'status' => 'ready',
        ]));
        $groupPage->assertOk()
            ->assertSee('ZZZ Beta PMC Export Group')
            ->assertSee('Export Current View');

        $groupCsv = $this->actingAs($chair)->get(route('academics.pmc.v041.surface.export', [
            'surface' => 'course-groups',
            'search' => 'Beta PMC Export Group',
            'status' => 'ready',
        ]))->streamedContent();
        $this->assertStringContainsString('ZZZ Beta PMC Export Group', $groupCsv);

        $plannerPage = $this->actingAs($chair)->get(route('academics.pmc.timetable-planner.index', [
            'search' => 'Beta PMC Export Group',
            'status' => 'scheduled',
            'sort' => 'confidence',
            'direction' => 'desc',
        ]));
        $plannerPage->assertOk()
            ->assertSee('ZZZ Beta PMC Export Group')
            ->assertSee('Export Current View');

        $plannerCsv = $this->actingAs($chair)->get(route('academics.pmc.v041.surface.export', [
            'surface' => 'timetable-planner',
            'search' => 'Beta PMC Export Group',
            'status' => 'scheduled',
            'sort' => 'confidence',
            'direction' => 'desc',
        ]))->streamedContent();
        $this->assertStringContainsString('ZZZ Beta PMC Export Group', $plannerCsv);

        $this->assertSame(1, AcademicPmcExportLog::where('report_key', 'pmc_v041_timetable-planner')->latest('id')->firstOrFail()->row_count);
    }

    public function test_primary_pmc_views_do_not_have_placeholder_or_broken_action_links(): void
    {
        foreach ([
            'academics/pmc/v004/command.blade.php',
            'academics/pmc/v004/surface.blade.php',
            'academics/pmc/v004/approvals.blade.php',
            'academics/pmc/v041/dashboard.blade.php',
            'academics/pmc/v041/surface.blade.php',
        ] as $view) {
            $contents = file_get_contents(resource_path("views/{$view}"));

            $this->assertStringNotContainsString('href="#"', $contents, "{$view} contains a placeholder action link.");
            $this->assertStringNotContainsString("href='#'", $contents, "{$view} contains a placeholder action link.");
            $this->assertStringNotContainsString('Â', $contents, "{$view} contains mojibake output.");
            $this->assertStringNotContainsString('</form><form', $contents, "{$view} contains adjacent forms without stable layout markup.");
        }
    }

    public function test_program_chair_shared_shell_uses_manifest_grouped_sidebar_links(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $response = $this->actingAs($chair)->get(route('academics.pmc.command'));

        $response->assertOk()
            ->assertSee('PMC Command')
            ->assertSee('PMC Workspace')
            ->assertSee('Academics Governance')
            ->assertSee('Curriculum Governance')
            ->assertSee('Timetable Builder')
            ->assertSee('Student Success')
            ->assertSee('Faculty Allocation')
            ->assertSee('Subject Performance')
            ->assertSee(route('academics.workspaces.show', 'pmc'), false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }

    public function test_legacy_at_risk_students_list_uses_filtered_total_and_export_current_view(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $program = Program::where('is_active', true)->firstOrFail();
        $batch = Batch::where('program_id', $program->id)->firstOrFail();
        $term = Term::where('program_id', $program->id)->where('batch_id', $batch->id)->firstOrFail();
        $course = Course::where('department_id', $program->department_id)->firstOrFail();

        $visibleUser = User::create([
            'name' => 'ZZZ Legacy At Risk Student',
            'email' => 'legacy.at-risk.visible@example.test',
            'password' => 'password',
        ]);
        $visibleStudent = Student::create([
            'user_id' => $visibleUser->id,
            'department_id' => $program->department_id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'enrollment_number' => 'LEG-RISK-001',
            'roll_number' => 'LR001',
            'status' => 'active',
        ]);

        $hiddenUser = User::create([
            'name' => 'AAA Legacy Hidden Risk Student',
            'email' => 'legacy.at-risk.hidden@example.test',
            'password' => 'password',
        ]);
        $hiddenStudent = Student::create([
            'user_id' => $hiddenUser->id,
            'department_id' => $program->department_id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'enrollment_number' => 'LEG-RISK-002',
            'roll_number' => 'LR002',
            'status' => 'active',
        ]);

        foreach ([$visibleStudent, $hiddenStudent] as $student) {
            FeeDemand::create([
                'student_id' => $student->id,
                'term_id' => $term->id,
                'total_amount' => 100000,
                'scholarship_deduction' => 0,
                'final_amount' => 100000,
                'due_date' => now()->subDays(10)->toDateString(),
                'penalty_amount' => 0,
                'status' => 'pending',
            ]);
        }

        $params = [
            'search' => 'ZZZ Legacy',
            'risk' => 'financial',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'sort' => 'student',
            'direction' => 'asc',
        ];

        $this->actingAs($chair)
            ->get(route('chair.students.at-risk', $params))
            ->assertOk()
            ->assertSee('ZZZ Legacy At Risk Student')
            ->assertDontSee('AAA Legacy Hidden Risk Student')
            ->assertSee('Visible filter summary')
            ->assertSee('Search: ZZZ Legacy')
            ->assertSee('Risk: financial')
            ->assertSee('1</span> students flagged at-risk', false)
            ->assertSee(e(route('chair.students.at-risk.export', $params)), false)
            ->assertDontSee('â€”');

        $csv = $this->actingAs($chair)
            ->get(route('chair.students.at-risk.export', $params))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('ZZZ Legacy At Risk Student', $csv);
        $this->assertStringNotContainsString('AAA Legacy Hidden Risk Student', $csv);
    }

    public function test_scoped_pmc_and_program_role_rendered_navigation_links_are_reachable(): void
    {
        $cases = [
            ['pmc.manager@college.com', 'academics.pmc.command'],
            ['pmc.officer@college.com', 'academics.pmc.command'],
            ['hod@college.com', 'academics.program-leadership.index'],
            ['course.coordinator@college.com', 'academics.course-delivery.index'],
            ['faculty.mentor@college.com', 'academics.course-delivery.index'],
        ];

        foreach ($cases as [$email, $route]) {
            $user = User::where('email', $email)->firstOrFail();
            $html = $this->actingAs($user)->get(route($route))
                ->assertOk()
                ->assertDontSee('SERVICE ERROR')
                ->assertDontSee('Whoops')
                ->assertDontSee('Laravel')
                ->content();

            foreach ($this->internalGetLinks($html) as $path) {
                $status = $this->actingAs($user)->get($path)->getStatusCode();
                $this->assertNotContains($status, [403, 404, 500], "{$email} visible link {$path} returned a blocked/broken status.");
            }
        }
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
