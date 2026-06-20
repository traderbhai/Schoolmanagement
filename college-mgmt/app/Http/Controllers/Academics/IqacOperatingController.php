<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Services\AcademicAccessPolicyService;
use App\Services\AcademicIqacOperatingService;
use Illuminate\Http\Request;

class IqacOperatingController extends Controller
{
    public function __construct(
        private AcademicAccessPolicyService $policy,
        private AcademicIqacOperatingService $iqac
    ) {}

    public function index(Request $request)
    {
        $this->authorizeIqac($request);

        return view('academics.iqac.dashboard', $this->iqac->dashboard($request->user()));
    }

    public function obeReadiness(Request $request) { return $this->section($request, 'obe-readiness'); }
    public function attainmentMonitoring(Request $request) { return $this->section($request, 'attainment-monitoring'); }
    public function feedbackQuality(Request $request) { return $this->section($request, 'feedback-quality'); }
    public function auditCompliance(Request $request) { return $this->section($request, 'audit-compliance'); }

    public function reports(Request $request)
    {
        $this->authorizeIqac($request);

        return view('academics.iqac.section', [
            'section' => [
                'title' => 'IQAC Reports',
                'description' => 'Export-ready quality indicators for OBE, attainment, feedback, surveys, and audit evidence.',
                'metrics' => [],
                'items' => collect($this->iqac->reports($request->user()))->map(fn ($report) => [
                    'title' => $report['label'],
                    'subtitle' => 'Current filtered result: ' . $report['count'],
                    'status' => 'Open report',
                    'action' => $report['route'],
                ])->values(),
                'filters' => $request->query(),
                'filter_summary' => 'Showing all scoped IQAC reports.',
            ],
        ]);
    }

    private function section(Request $request, string $section)
    {
        $this->authorizeIqac($request);

        return view('academics.iqac.section', [
            'section' => $this->iqac->section($request->user(), $section, $request->query()),
        ]);
    }

    private function authorizeIqac(Request $request): void
    {
        $user = $request->user();
        $this->policy->authorizeRead($user);

        abort_unless(
            $user->hasAnyRole([
                'admin',
                'director',
                'academic_department_owner',
                'dean_academics',
                'iqac_head',
                'iqac_manager',
                'iqac_officer',
            ]),
            403
        );
    }
}
