<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicPmcWorkItem;
use App\Services\AcademicAccessPolicyService;
use App\Services\AcademicPmcOperatingService;
use App\Services\AcademicPmcV003Service;
use Illuminate\Http\Request;

class PmcOperatingController extends Controller
{
    public function __construct(
        private AcademicAccessPolicyService $policy,
        private AcademicPmcOperatingService $pmc,
        private AcademicPmcV003Service $pmcV003
    ) {}

    public function index(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.dashboard', $this->pmc->dashboard($request->user()));
    }

    public function curriculumReadiness(Request $request)
    {
        return $this->section($request, 'curriculum-readiness');
    }

    public function facultyAllocation(Request $request)
    {
        return $this->section($request, 'faculty-allocation');
    }

    public function timetableReadiness(Request $request)
    {
        return $this->section($request, 'timetable-readiness');
    }

    public function studentMonitoring(Request $request)
    {
        return $this->section($request, 'student-monitoring');
    }

    public function reports(Request $request)
    {
        $this->authorizePmc($request);

        $legacyReports = collect($this->pmc->reports($request->user()))
            ->map(fn ($report, $key) => $report + ['key' => is_string($key) ? $key : str($report['label'])->slug('_')->toString()])
            ->values();

        return view('academics.pmc.v003.reports', [
            'reports' => $legacyReports->merge($this->pmcV003->reports()),
        ]);
    }

    public function command(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.command', $this->pmcV003->command($request->user()));
    }

    public function workbench(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.workbench', $this->pmcV003->workbench($request->query('type', 'all')));
    }

    public function curriculumGovernance(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.curriculum', $this->pmcV003->curriculum());
    }

    public function facultyWorkload(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.faculty', $this->pmcV003->faculty());
    }

    public function timetableControl(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.timetable', $this->pmcV003->timetable());
    }

    public function studentSuccess(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.student-success', $this->pmcV003->studentSuccess());
    }

    public function reviews(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.reviews', $this->pmcV003->reviews());
    }

    public function storeWorkItem(Request $request)
    {
        $this->authorizePmc($request);
        $data = $request->validate([
            'work_type' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'owner_user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|string|max:40',
            'status' => 'nullable|string|max:60',
            'severity' => 'nullable|string|max:40',
            'due_at' => 'nullable|date',
        ]);
        $this->pmcV003->createWorkItem($request->user(), $data);

        return back()->with('success', 'PMC work item created.');
    }

    public function updateWorkItem(Request $request, AcademicPmcWorkItem $item)
    {
        $this->authorizePmc($request);
        $data = $request->validate([
            'owner_user_id' => 'nullable|exists:users,id',
            'priority' => 'required|string|max:40',
            'status' => 'required|string|max:60',
            'severity' => 'required|string|max:40',
            'due_at' => 'nullable|date',
        ]);
        $this->pmcV003->updateWorkItem($item, $data);

        return back()->with('success', 'PMC work item updated.');
    }

    public function storeReview(Request $request)
    {
        $this->authorizePmc($request);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'review_type' => 'required|string|max:80',
            'scheduled_for' => 'nullable|date',
            'agenda' => 'nullable|string|max:3000',
        ]);
        $this->pmcV003->createReview($request->user(), $data);

        return back()->with('success', 'PMC review created.');
    }

    public function storeSavedView(Request $request)
    {
        $this->authorizePmc($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'surface' => 'required|string|max:80',
            'filters' => 'nullable|array',
            'is_default' => 'nullable|boolean',
        ]);
        $this->pmcV003->saveView($request->user(), $data);

        return back()->with('success', 'PMC saved view stored.');
    }

    public function export(Request $request, string $report)
    {
        $this->authorizePmc($request);

        return $this->pmcV003->export($report, $request->user(), $request->query());
    }

    private function section(Request $request, string $section)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.section', [
            'section' => $this->pmc->section($request->user(), $section),
        ]);
    }

    private function authorizePmc(Request $request): void
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
                'pmc_officer',
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
