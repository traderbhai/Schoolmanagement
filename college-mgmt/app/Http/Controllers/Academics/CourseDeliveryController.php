<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Services\AcademicAccessPolicyService;
use App\Services\AcademicCourseDeliveryService;
use Illuminate\Http\Request;

class CourseDeliveryController extends Controller
{
    public function __construct(
        private AcademicAccessPolicyService $policy,
        private AcademicCourseDeliveryService $delivery
    ) {}

    public function index(Request $request)
    {
        $this->authorizeCourseDelivery($request);

        return view('academics.course-delivery.dashboard', $this->delivery->dashboard($request->user()));
    }

    public function courseLoad(Request $request) { return $this->section($request, 'course-load'); }
    public function sessionDelivery(Request $request) { return $this->section($request, 'session-delivery'); }
    public function attendanceInterventions(Request $request) { return $this->section($request, 'attendance-interventions'); }
    public function courseEngagement(Request $request) { return $this->section($request, 'course-engagement'); }
    public function mentorActions(Request $request) { return $this->section($request, 'mentor-actions'); }

    public function reports(Request $request)
    {
        $this->authorizeCourseDelivery($request);

        return view('academics.course-delivery.section', [
            'section' => [
                'title' => 'Course Delivery Reports',
                'description' => 'Export-ready faculty workload, session, attendance, engagement, and mentor indicators.',
                'metrics' => [],
                'items' => collect($this->delivery->reports($request->user()))->map(fn ($report) => [
                    'title' => $report['label'],
                    'subtitle' => 'Current filtered result: ' . $report['count'],
                    'status' => 'Open report',
                    'action' => $report['route'],
                ])->values(),
                'filters' => $request->query(),
                'filter_summary' => 'Showing all scoped course-delivery reports.',
            ],
        ]);
    }

    private function section(Request $request, string $section)
    {
        $this->authorizeCourseDelivery($request);

        return view('academics.course-delivery.section', [
            'section' => $this->delivery->section($request->user(), $section, $request->query()),
        ]);
    }

    private function authorizeCourseDelivery(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $this->policy->authorizeCourseDelivery($user);
    }
}
