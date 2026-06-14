<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Services\AcademicAccessPolicyService;
use App\Services\AcademicCoeOperatingService;
use Illuminate\Http\Request;

class CoeOperatingController extends Controller
{
    public function __construct(
        private AcademicAccessPolicyService $policy,
        private AcademicCoeOperatingService $coe
    ) {}

    public function index(Request $request)
    {
        $this->authorizeCoe($request);

        return view('academics.coe.dashboard', $this->coe->dashboard($request->user()));
    }

    public function examReadiness(Request $request)
    {
        return $this->section($request, 'exam-readiness');
    }

    public function marksResults(Request $request)
    {
        return $this->section($request, 'marks-results');
    }

    public function hallTicketReadiness(Request $request)
    {
        return $this->section($request, 'hall-ticket-readiness');
    }

    public function transcripts(Request $request)
    {
        return $this->section($request, 'transcripts');
    }

    public function appealsAnomalies(Request $request)
    {
        return $this->section($request, 'appeals-anomalies');
    }

    public function reports(Request $request)
    {
        $this->authorizeCoe($request);

        return view('academics.coe.section', [
            'section' => [
                'title' => 'CoE Reports',
                'description' => 'Export-ready examination indicators for schedules, results, hall tickets, transcripts, appeals, and anomalies.',
                'metrics' => [],
                'items' => collect($this->coe->reports($request->user()))->map(fn ($report) => [
                    'title' => $report['label'],
                    'subtitle' => 'Current filtered result: ' . $report['count'],
                    'status' => 'Open report',
                    'action' => $report['route'],
                ])->values(),
            ],
        ]);
    }

    private function section(Request $request, string $section)
    {
        $this->authorizeCoe($request);

        return view('academics.coe.section', [
            'section' => $this->coe->section($request->user(), $section),
        ]);
    }

    private function authorizeCoe(Request $request): void
    {
        $user = $request->user();
        $this->policy->authorizeRead($user);

        abort_unless(
            $user->hasAnyRole([
                'admin',
                'director',
                'academic_department_owner',
                'dean_academics',
                'coe',
                'exam_cell',
                'exam_manager',
                'exam_officer',
            ]),
            403
        );
    }
}
