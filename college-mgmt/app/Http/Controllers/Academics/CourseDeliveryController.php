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
            ],
        ]);
    }

    private function section(Request $request, string $section)
    {
        $this->authorizeCourseDelivery($request);

        return view('academics.course-delivery.section', [
            'section' => $this->delivery->section($request->user(), $section),
        ]);
    }

    private function authorizeCourseDelivery(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $this->policy->canViewAcademics($user) || $user->hasAnyRole(['teacher', 'faculty']),
            403
        );

        abort_unless(
            $user->hasAnyRole([
                'admin',
                'director',
                'academic_department_owner',
                'dean_academics',
                'pmc_head',
                'pmc_manager',
                'pmc_officer',
                'program_chair',
                'program_director',
                'program_leader',
                'hod',
                'semester_coordinator',
                'course_coordinator',
                'faculty_mentor',
                'teacher',
                'faculty',
            ]),
            403
        );
    }
}
