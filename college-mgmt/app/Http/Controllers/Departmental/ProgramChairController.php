<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Program, Student, Subject, Exam, ExamResult, Attendance, Batch, Term, RoleProgramAssignment, TimetableEntry, ApprovalWorkflow, Applicant, SeatMatrix,
    TimetableVersion, ElectiveRegistrationWindow, LeaveApplication, AttendanceCondonation, StudentGrievance};
use App\Helpers\AccessControl;
use App\Services\{FacultyWorkloadService, ClassroomCapacityService, SoftConstraintService, LoadBalancingService};
use App\Models\Classroom;
use Illuminate\Http\Request;

class ProgramChairController extends Controller
{
    protected function getAssignedProgramIds(): array
    {
        $user = auth()->user();
        if ($user->hasRole(['admin', 'dean_academics'])) {
            return Program::where('is_active', true)->pluck('id')->toArray();
        }
        return RoleProgramAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('program_id')
            ->toArray();
    }

    public function dashboard()
    {
        $programIds = $this->getAssignedProgramIds();
        $programs = Program::whereIn('id', $programIds)->with(['batches'])->get();

        $activeStudents = Student::when(!empty($programIds), fn($q) => $q->whereIn('program_id', $programIds))
            ->where('status', 'active')->count();

        $currentTerm = Term::latest('start_date')->first();
        $subjectsThisTerm = Subject::when(!empty($programIds), fn($q) => $q->whereIn('program_id', $programIds))
            ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
            ->count();

        $examCount = Exam::whereYear('exam_date', now()->year)
            ->when(!empty($programIds), fn($q) => $q->whereIn('program_id', $programIds))
            ->count();

        // Average marks
        $avgMarks = ExamResult::whereHas('exam', fn($q) => $q->whereIn('program_id', $programIds))->avg('marks_obtained');
        $avgMarks = $avgMarks ? round($avgMarks, 1) : '—';

        // Pending approvals
        $pendingApprovals = ApprovalWorkflow::where('approver_role', 'program_chair')
            ->where('status', 'pending')->count();

        // Attendance % for these programs
        $attTotal = Attendance::whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))->count();
        $attPresent = Attendance::whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))->where('status', 'present')->count();
        $attendancePct = $attTotal > 0 ? round(($attPresent / $attTotal) * 100, 1) : 0;

        // Recent exams
        $recentExams = Exam::when(!empty($programIds), fn($q) => $q->whereIn('program_id', $programIds))
            ->with('subject')
            ->latest('exam_date')
            ->take(6)
            ->get()
            ->map(function($exam) {
                $results = ExamResult::where('exam_id', $exam->id)->get();
                $exam->result_count = $results->count();
                $exam->pass_count = $results->where('marks_obtained', '>=', ($exam->passing_marks ?? 40))->count();
                return $exam;
            });

        // Subjects with attendance < 75%
        $lowAttSubjects = Subject::when(!empty($programIds), fn($q) => $q->whereIn('program_id', $programIds))
            ->with('program')
            ->take(20)->get()
            ->map(function($subject) {
                $total = Attendance::where('subject_id', $subject->id)->count();
                $present = Attendance::where('subject_id', $subject->id)->where('status', 'present')->count();
                $subject->attendance_pct = $total > 0 ? round(($present / $total) * 100, 1) : null;
                return $subject;
            })
            ->filter(fn($s) => $s->attendance_pct !== null && $s->attendance_pct < 75)
            ->sortBy('attendance_pct')
            ->take(5);

        // At-risk students (attendance < 75% in any subject, quick approximation)
        $atRiskStudents = collect();
        try {
            $pmcStudentCtrl = new \App\Http\Controllers\Departmental\PmcStudentController();
            // Use a lightweight in-line approach for the dashboard
            $allStudents = Student::whereIn('program_id', $programIds)
                ->where('status', 'active')->with(['user','batch'])->take(100)->get();
            $atRiskStudents = $allStudents->filter(function ($student) {
                $attBySubject = Attendance::where('student_id', $student->id)
                    ->selectRaw('subject_id, COUNT(*) as total, SUM(CASE WHEN status="present" THEN 1 ELSE 0 END) as present')
                    ->groupBy('subject_id')->get();
                $risks = [];
                if ($attBySubject->filter(fn($r) => $r->total > 0 && ($r->present/$r->total) < 0.75)->isNotEmpty()) $risks[] = 'attendance';
                $results = ExamResult::where('student_id', $student->id)->get();
                if ($results->isNotEmpty()) {
                    $arrears = $results->filter(fn($r) => $r->exam && ($r->marks_obtained / max($r->exam->total_marks??100,1))*100 < 35);
                    if ($arrears->isNotEmpty()) $risks[] = 'arrear';
                    $avg = $results->avg(fn($r) => $r->exam ? ($r->marks_obtained/max($r->exam->total_marks??100,1))*100 : null);
                    if ($avg !== null && $avg < 50) $risks[] = 'academic';
                }
                $student->risks = $risks;
                return !empty($risks);
            })->take(8);
        } catch (\Throwable $e) {}

        // Faculty workload summary
        $workloadSummary = TimetableEntry::when(!empty($programIds), fn($q) => $q->whereIn('program_id', $programIds))
            ->when($currentTerm ?? null, fn($q) => $q->where('term_id', ($currentTerm = Term::latest('start_date')->first())?->id))
            ->where('is_active', true)
            ->selectRaw('teacher_id, COUNT(*) as sessions')
            ->groupBy('teacher_id')
            ->with('teacher.user')
            ->orderByDesc('sessions')
            ->take(8)
            ->get();

        // Timetable versions
        $timetableVersions = TimetableVersion::whereIn('program_id', $programIds)
            ->with(['program','batch'])
            ->orderByDesc('id')
            ->take(6)
            ->get();

        // Elective windows
        $electiveWindows = ElectiveRegistrationWindow::whereIn('program_id', $programIds)
            ->with('program')
            ->orderByDesc('id')
            ->take(4)
            ->get();

        // Pending counts
        $pendingLeaves = LeaveApplication::whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))
            ->where('status', 'pending')->count();
        $pendingCondonations = AttendanceCondonation::whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))
            ->where('status', 'pending')->count();
        $openGrievances = StudentGrievance::whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))
            ->whereIn('status', ['open','under_review'])->count();

        return view('departmental.program-chair.dashboard', compact(
            'activeStudents', 'subjectsThisTerm', 'examCount', 'avgMarks',
            'pendingApprovals', 'attendancePct', 'recentExams', 'lowAttSubjects', 'programs',
            'atRiskStudents', 'workloadSummary', 'timetableVersions', 'electiveWindows',
            'pendingLeaves', 'pendingCondonations', 'openGrievances'
        ));
    }

    public function students(Request $request)
    {
        $programIds = $this->getAssignedProgramIds();

        $query = Student::with(['user', 'program', 'batch'])
            ->whereIn('program_id', $programIds);

        if ($request->filled('batch_id'))   $query->where('batch_id', $request->batch_id);
        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('program_id')) $query->where('program_id', $request->program_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$s}%"))
                ->orWhere('enrollment_number', 'like', "%{$s}%"));
        }

        $students = $query->paginate(25)->withQueryString();
        $programs = Program::whereIn('id', $programIds)->get();
        $batches  = Batch::whereIn('program_id', $programIds)->orderBy('name')->get();

        return view('departmental.program-chair.students', compact('students', 'programs', 'batches'));
    }

    public function curriculum()
    {
        $programIds = $this->getAssignedProgramIds();

        $subjects = Subject::whereIn('program_id', $programIds)
            ->with('program')
            ->orderBy('program_id')
            ->orderBy('term_number')
            ->orderBy('name')
            ->get()
            ->groupBy(['program_id', 'term_number']);

        $programs = Program::whereIn('id', $programIds)->get()->keyBy('id');

        return view('departmental.program-chair.curriculum', compact('subjects', 'programs'));
    }

    public function timetable()
    {
        $programIds = $this->getAssignedProgramIds();

        $entries = TimetableEntry::whereHas('subject', fn($q) => $q->whereIn('program_id', $programIds))
            ->with(['subject', 'teacher.user', 'classroom', 'timetableSlot', 'batch'])
            ->orderBy('day_of_week')
            ->get()
            ->groupBy('day_of_week');

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return view('departmental.program-chair.timetable', compact('entries', 'days'));
    }

    public function exams()
    {
        $programIds = $this->getAssignedProgramIds();

        $upcoming = Exam::whereIn('program_id', $programIds)
            ->where('exam_date', '>=', now()->toDateString())
            ->with(['program', 'subject', 'term'])
            ->orderBy('exam_date')
            ->get();

        $past = Exam::whereIn('program_id', $programIds)
            ->where('exam_date', '<', now()->toDateString())
            ->with(['program', 'subject', 'term'])
            ->orderByDesc('exam_date')
            ->take(30)
            ->get();

        return view('departmental.program-chair.exams', compact('upcoming', 'past'));
    }

    public function approvals(Request $request)
    {
        $query = ApprovalWorkflow::where('approver_role', 'program_chair')
            ->where('status', 'pending')
            ->with(['approvable' => function ($q) {
                $q->with(['user', 'program', 'batch']);
            }])
            ->latest();

        $approvals = $query->paginate(20)->withQueryString();

        return view('departmental.program-chair.approvals.index', compact('approvals'));
    }

    public function approve(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        // Check seat capacity before approving
        $applicant = $approval->approvable;
        $seatMatrix = SeatMatrix::where('program_id', $applicant->program_id)->first();

        if ($seatMatrix) {
            $filledSeats = Applicant::where('program_id', $applicant->program_id)
                ->whereIn('status', ['offer_accepted', 'enrolled'])
                ->count();

            if ($filledSeats >= $seatMatrix->total_seats) {
                return back()->with('error', 'Program capacity is full. Cannot approve additional applicants.');
            }
        }

        $approval->update([
            'status'      => 'approved',
            'approver_id' => auth()->id(),
            'remarks'     => $request->remarks,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Approval granted. All approvals complete.');
    }

    public function reject(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $approval->update([
            'status'      => 'rejected',
            'approver_id' => auth()->id(),
            'remarks'     => $request->rejection_reason,
            'approved_at' => now(),
        ]);

        return back()->with('error', 'Approval rejected.');
    }

    // ── Faculty Workload Report ───────────────────────────────────────────────
    public function workloadReport(Request $request)
    {
        $programIds = $this->getAssignedProgramIds();
        $programs = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms = Term::orderBy('start_date', 'desc')->take(8)->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id) : $programs->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : Term::latest('start_date')->first();

        $report = [];
        $summary = [];

        if ($selectedProgram && $selectedTerm) {
            $service = app(FacultyWorkloadService::class);
            $report = $service->getWorkloadReport($selectedProgram->id, $selectedTerm->id);
            $summary = $service->getSummary($report);
        }

        return view('departmental.program-chair.workload', compact(
            'programs', 'terms', 'selectedProgram', 'selectedTerm', 'report', 'summary'
        ));
    }

    public function workloadExport(Request $request)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'term_id'    => 'required|exists:terms,id',
        ]);

        $service = app(FacultyWorkloadService::class);
        $csv = $service->exportAsCSV($request->program_id, $request->term_id);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="faculty_workload_' . date('Y-m-d') . '.csv"');
    }

    // ── Classroom Capacity Validation ─────────────────────────────────────────
    public function capacityReport(Request $request)
    {
        $programIds = $this->getAssignedProgramIds();
        $programs = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms = Term::orderBy('start_date', 'desc')->take(8)->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id) : $programs->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : Term::latest('start_date')->first();

        $utilization = [];
        $violations = [];
        $summary = [];

        if ($selectedProgram && $selectedTerm) {
            $service = app(ClassroomCapacityService::class);
            $utilization = $service->getUtilizationReport($selectedProgram->id, $selectedTerm->id);
            $violations = $service->findCapacityViolations($selectedProgram->id, $selectedTerm->id);
            $summary = $service->getSummary($violations);
        }

        return view('departmental.program-chair.capacity', compact(
            'programs', 'terms', 'selectedProgram', 'selectedTerm',
            'utilization', 'violations', 'summary'
        ));
    }

    // ── Room Utilization Report ──────────────────────────────────────────────
    public function roomUtilization(Request $request)
    {
        $programIds = $this->getAssignedProgramIds();
        $programs = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms = Term::orderBy('start_date', 'desc')->take(8)->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id) : $programs->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : Term::latest('start_date')->first();

        $roomStats = [];
        $summary = [];

        if ($selectedProgram && $selectedTerm) {
            $service = app(ClassroomCapacityService::class);
            $roomStats = $service->getUtilizationReport($selectedProgram->id, $selectedTerm->id);

            // Calculate summary
            $totalRooms = count($roomStats);
            $fullyUtilized = count(array_filter($roomStats, fn($r) => $r['status'] === 'fully-utilized'));
            $wellUtilized = count(array_filter($roomStats, fn($r) => $r['status'] === 'well-utilized'));
            $underUtilized = count(array_filter($roomStats, fn($r) => $r['status'] === 'under-utilized'));
            $overCapacity = count(array_filter($roomStats, fn($r) => $r['has_issues']));

            $avgUtilization = $totalRooms > 0
                ? round(array_sum(array_column($roomStats, 'max_utilization')) / $totalRooms, 1)
                : 0;

            $summary = [
                'total_rooms' => $totalRooms,
                'fully_utilized' => $fullyUtilized,
                'well_utilized' => $wellUtilized,
                'under_utilized' => $underUtilized,
                'over_capacity' => $overCapacity,
                'avg_utilization' => $avgUtilization,
            ];
        }

        return view('departmental.program-chair.room-utilization', compact(
            'programs', 'terms', 'selectedProgram', 'selectedTerm',
            'roomStats', 'summary'
        ));
    }

    // ── Soft Constraint Audit ────────────────────────────────────────────────
    public function softConstraints(Request $request)
    {
        $programIds = $this->getAssignedProgramIds();
        $programs = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms = Term::orderBy('start_date', 'desc')->take(8)->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id) : $programs->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : Term::latest('start_date')->first();

        $selectedBatch = $request->filled('batch_id')
            ? Batch::find($request->batch_id) : null;

        $batches = $selectedProgram
            ? Batch::where('program_id', $selectedProgram->id)->orderBy('name')->get()
            : [];

        $auditResult = [];

        if ($selectedProgram && $selectedTerm) {
            $service = app(SoftConstraintService::class);
            $auditResult = $service->auditTermConstraints(
                $selectedTerm->id,
                $selectedProgram->id,
                $selectedBatch?->id
            );
        }

        return view('departmental.program-chair.soft-constraints', compact(
            'programs', 'terms', 'batches', 'selectedProgram', 'selectedTerm',
            'selectedBatch', 'auditResult'
        ));
    }

    // ── Load Balancing & Analytics ───────────────────────────────────────────
    public function loadBalance(Request $request)
    {
        $programIds = $this->getAssignedProgramIds();
        $programs = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms = Term::orderBy('start_date', 'desc')->take(8)->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id) : $programs->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : Term::latest('start_date')->first();

        $analysis = [];

        if ($selectedProgram && $selectedTerm) {
            $service = app(LoadBalancingService::class);
            $analysis = $service->analyzeLoadBalance($selectedTerm->id, $selectedProgram->id);
        }

        return view('departmental.program-chair.load-balance', compact(
            'programs', 'terms', 'selectedProgram', 'selectedTerm', 'analysis'
        ));
    }

    public function analytics(Request $request)
    {
        $programIds = $this->getAssignedProgramIds();
        $programs = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms = Term::orderBy('start_date', 'desc')->take(8)->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id) : $programs->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : Term::latest('start_date')->first();

        $dashboardData = [];

        if ($selectedProgram && $selectedTerm) {
            $dashboardData = [
                'workload' => app(FacultyWorkloadService::class)->getSummary(
                    app(FacultyWorkloadService::class)->getWorkloadReport($selectedProgram->id, $selectedTerm->id)
                ),
                'capacity' => app(ClassroomCapacityService::class)->getSummary(
                    app(ClassroomCapacityService::class)->findCapacityViolations($selectedProgram->id, $selectedTerm->id)
                ),
                'loadBalance' => app(LoadBalancingService::class)->analyzeLoadBalance($selectedTerm->id, $selectedProgram->id)['stats'],
                'totalEntries' => TimetableEntry::where('program_id', $selectedProgram->id)
                    ->where('term_id', $selectedTerm->id)
                    ->count(),
            ];
        }

        return view('departmental.program-chair.analytics', compact(
            'programs', 'terms', 'selectedProgram', 'selectedTerm', 'dashboardData'
        ));
    }
}
