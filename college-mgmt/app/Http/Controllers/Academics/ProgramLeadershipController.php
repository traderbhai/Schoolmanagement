<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Services\AcademicAccessPolicyService;
use App\Services\AcademicProgramLeadershipService;
use Illuminate\Http\Request;

class ProgramLeadershipController extends Controller
{
    public function __construct(
        private AcademicAccessPolicyService $policy,
        private AcademicProgramLeadershipService $programs
    ) {}

    public function index(Request $request)
    {
        $this->authorizeProgramLeadership($request);

        return view('academics.program-leadership.dashboard', $this->programs->dashboard($request->user()));
    }

    public function portfolio(Request $request) { return $this->section($request, 'portfolio'); }
    public function courseDelivery(Request $request) { return $this->section($request, 'course-delivery'); }
    public function studentSuccess(Request $request) { return $this->section($request, 'student-success'); }
    public function qualitySignals(Request $request) { return $this->section($request, 'quality-signals'); }

    public function reports(Request $request)
    {
        $this->authorizeProgramLeadership($request);

        return view('academics.program-leadership.section', [
            'section' => [
                'title' => 'Program Leadership Reports',
                'description' => 'Export-ready program portfolio, delivery, student success, and quality indicators.',
                'metrics' => [],
                'items' => collect($this->programs->reports($request->user()))->map(fn ($report) => [
                    'title' => $report['label'],
                    'subtitle' => 'Current filtered result: ' . $report['count'],
                    'status' => 'Open report',
                    'action' => $report['route'],
                ])->values(),
                'filters' => $request->query(),
                'filter_summary' => 'Showing all scoped program leadership reports.',
            ],
        ]);
    }

    private function section(Request $request, string $section)
    {
        $this->authorizeProgramLeadership($request);

        return view('academics.program-leadership.section', [
            'section' => $this->programs->section($request->user(), $section, $request->query()),
        ]);
    }

    private function authorizeProgramLeadership(Request $request): void
    {
        $user = $request->user();
        $this->policy->authorizeRead($user);

        abort_unless(
            $user->hasAnyRole([
                'admin',
                'director',
                'academic_department_owner',
                'dean_academics',
                'pmc_head',
                'pmc_manager',
                'program_chair',
                'program_director',
                'program_leader',
                'hod',
                'semester_coordinator',
                'course_coordinator',
                'faculty_mentor',
            ]),
            403
        );
    }
}
