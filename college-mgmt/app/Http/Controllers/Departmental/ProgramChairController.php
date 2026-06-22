<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{AcademicPmcTimetableGenerationItem, Program, Student, Subject, Exam, ExamResult, Attendance, Batch, Term, RoleProgramAssignment, TimetableEntry, ApprovalWorkflow, Applicant, SeatMatrix,
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
        $hasAssignedPrograms = !empty($programIds);

        $activeStudents = Student::whereIn('program_id', $programIds)
            ->where('status', 'active')->count();

        $currentTerm = Term::latest('start_date')->first();
        $subjectsThisTerm = Subject::whereIn('program_id', $programIds)
            ->when($currentTerm, fn($q) => $q->where('term_number', $currentTerm->term_number))
            ->count();

        $examCount = Exam::whereYear('exam_date', now()->year)
            ->whereIn('program_id', $programIds)
            ->count();

        // Average marks
        $avgMarks = ExamResult::whereHas('exam', fn($q) => $q
            ->whereNotNull('published_at')
            ->whereIn('program_id', $programIds))->avg('marks_obtained');
        $avgMarks = $avgMarks ? round($avgMarks, 1) : '—';

        // Pending approvals
        $pendingApprovals = $this->pendingProgramChairApprovals($programIds)->count();
        $avgMarks = is_numeric($avgMarks) ? $avgMarks : '-';

        // Attendance % for these programs
        $attTotal = $this->officialAttendanceQuery()
            ->whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))
            ->count();
        $attPresent = $this->officialAttendanceQuery()
            ->whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))
            ->where('status', 'present')
            ->count();
        $attendancePct = $attTotal > 0 ? round(($attPresent / $attTotal) * 100, 1) : 0;

        // Recent exams
        $recentExams = Exam::whereIn('program_id', $programIds)
            ->whereNotNull('published_at')
            ->with(['subject', 'results'])
            ->latest('exam_date')
            ->take(6)
            ->get()
            ->map(function($exam) {
                $results = $exam->results;
                $exam->result_count = $results->count();
                $exam->pass_count = $results->where('marks_obtained', '>=', ($exam->passing_marks ?? 40))->count();
                return $exam;
            });

        // Pre-aggregate attendance per subject (join through timetable_entries)
        $subjectAttData = \App\Models\Attendance::join('timetable_entries', 'attendances.timetable_entry_id', '=', 'timetable_entries.id')
            ->tap(fn ($query) => $this->publishedTimetableJoinScope($query))
            ->selectRaw('timetable_entries.subject_id, COUNT(*) as total, SUM(CASE WHEN attendances.status="present" THEN 1 ELSE 0 END) as present_count')
            ->groupBy('timetable_entries.subject_id')
            ->get()
            ->keyBy('subject_id');

        // Subjects with attendance < 75%
        $lowAttSubjects = Subject::whereIn('program_id', $programIds)
            ->with('program')
            ->take(20)->get()
            ->map(function($subject) use ($subjectAttData) {
                $att = $subjectAttData->get($subject->id);
                $total = $att?->total ?? 0;
                $present = $att?->present_count ?? 0;
                $subject->attendance_pct = $total > 0 ? round(($present / $total) * 100, 1) : null;
                return $subject;
            })
            ->filter(fn($s) => $s->attendance_pct !== null && $s->attendance_pct < 75)
            ->sortBy('attendance_pct')
            ->take(5);

        // At-risk students (attendance < 75% in any subject, quick approximation)
        $atRiskStudents = collect();
        try {
            $studentIds = Student::whereIn('program_id', $programIds)->where('status', 'active')->take(100)->pluck('id');

            $studentAttBySubject = \App\Models\Attendance::whereIn('attendances.student_id', $studentIds)
                ->join('timetable_entries', 'attendances.timetable_entry_id', '=', 'timetable_entries.id')
                ->tap(fn ($query) => $this->publishedTimetableJoinScope($query))
                ->selectRaw('attendances.student_id, timetable_entries.subject_id, COUNT(*) as total, SUM(CASE WHEN attendances.status="present" THEN 1 ELSE 0 END) as present_count')
                ->groupBy('attendances.student_id', 'timetable_entries.subject_id')
                ->get()
                ->groupBy('student_id');

            $studentResults = \App\Models\ExamResult::whereIn('student_id', $studentIds)
                ->whereHas('exam', fn($q) => $q->whereNotNull('published_at'))
                ->with('exam')
                ->get()
                ->groupBy('student_id');

            $allStudents = Student::whereIn('id', $studentIds)->with(['user','batch'])->get();
            $atRiskStudents = $allStudents->filter(function ($student) use ($studentAttBySubject, $studentResults) {
                $attBySubject = $studentAttBySubject->get($student->id, collect());
                $risks = [];
                if ($attBySubject->filter(fn($r) => $r->total > 0 && ($r->present_count/$r->total) < 0.75)->isNotEmpty()) $risks[] = 'attendance';
                $results = $studentResults->get($student->id, collect());
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

        $workloadSummary = $this->dashboardWorkloadSummary($programIds, $currentTerm);

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

        $chairPriority = $this->chairPriority(
            $hasAssignedPrograms,
            $pendingApprovals,
            $pendingLeaves,
            $pendingCondonations,
            $openGrievances,
            $atRiskStudents->count(),
            $lowAttSubjects->count(),
            $timetableVersions->where('status', 'published')->count(),
            $subjectsThisTerm
        );

        return view('departmental.program-chair.dashboard', compact(
            'activeStudents', 'subjectsThisTerm', 'examCount', 'avgMarks',
            'pendingApprovals', 'attendancePct', 'recentExams', 'lowAttSubjects', 'programs',
            'atRiskStudents', 'workloadSummary', 'timetableVersions', 'electiveWindows',
            'pendingLeaves', 'pendingCondonations', 'openGrievances', 'chairPriority'
        ));
    }

    private function chairPriority(
        bool $hasAssignedPrograms,
        int $pendingApprovals,
        int $pendingLeaves,
        int $pendingCondonations,
        int $openGrievances,
        int $atRiskCount,
        int $lowAttendanceSubjectCount,
        int $publishedTimetableCount,
        int $subjectsThisTerm
    ): array {
        if (!$hasAssignedPrograms) {
            return [
                'level' => 'warning',
                'title' => 'Program assignment needed',
                'body' => 'No active program is assigned to your Program Chair role. Ask an administrator to scope your access before reviewing students, timetable, or approvals.',
                'route' => route('admin.role-assignments.index'),
                'action' => 'Review Assignments',
            ];
        }

        if ($pendingApprovals > 0) {
            return [
                'level' => 'warning',
                'title' => "Review {$pendingApprovals} pending approval" . ($pendingApprovals === 1 ? '' : 's'),
                'body' => 'Admission and capacity sign-offs should be cleared before seats and offers move forward.',
                'route' => route('chair.approvals'),
                'action' => 'Open Approvals',
            ];
        }

        if ($pendingLeaves > 0) {
            return [
                'level' => 'warning',
                'title' => "Review {$pendingLeaves} student leave request" . ($pendingLeaves === 1 ? '' : 's'),
                'body' => 'Pending leave decisions affect attendance, mentoring, and class follow-up.',
                'route' => route('chair.students.leaves'),
                'action' => 'Review Leaves',
            ];
        }

        if ($pendingCondonations > 0) {
            return [
                'level' => 'warning',
                'title' => "Review {$pendingCondonations} attendance condonation request" . ($pendingCondonations === 1 ? '' : 's'),
                'body' => 'Condonation decisions should be resolved before exam eligibility and hall-ticket workflows.',
                'route' => route('chair.students.condonations'),
                'action' => 'Review Condonations',
            ];
        }

        if ($openGrievances > 0) {
            return [
                'level' => 'danger',
                'title' => "Resolve {$openGrievances} open student grievance" . ($openGrievances === 1 ? '' : 's'),
                'body' => 'Open grievances need ownership and resolution notes before they become escalations.',
                'route' => route('chair.students.grievances'),
                'action' => 'Review Grievances',
            ];
        }

        if ($atRiskCount > 0 || $lowAttendanceSubjectCount > 0) {
            return [
                'level' => 'danger',
                'title' => 'Review at-risk student signals',
                'body' => "{$atRiskCount} student(s) and {$lowAttendanceSubjectCount} subject(s) need academic or attendance intervention.",
                'route' => route('chair.students.at-risk'),
                'action' => 'Open At-Risk List',
            ];
        }

        if ($publishedTimetableCount === 0) {
            return [
                'level' => 'warning',
                'title' => 'Publish timetable for assigned programs',
                'body' => 'No published timetable version is available yet. Publish a version so students and faculty have an official schedule.',
                'route' => route('chair.timetable.builder'),
                'action' => 'Open Timetable Builder',
            ];
        }

        if ($subjectsThisTerm === 0) {
            return [
                'level' => 'info',
                'title' => 'Review curriculum setup for the current term',
                'body' => 'No subjects are mapped for the current term. Verify curriculum and faculty assignments.',
                'route' => route('chair.curriculum.index'),
                'action' => 'Open Curriculum',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No urgent program action today',
            'body' => 'Use this time to review workload, performance reports, timetable quality, and curriculum readiness.',
            'route' => route('chair.reports.subject-performance'),
            'action' => 'View Reports',
        ];
    }

    private function dashboardWorkloadSummary(array $programIds, ?Term $term)
    {
        if (! $term) {
            return collect();
        }

        $rows = collect();
        $service = app(FacultyWorkloadService::class);

        foreach ($programIds as $programId) {
            foreach ($service->getWorkloadReport((int) $programId, (int) $term->id) as $row) {
                $rows->push((object) [
                    'teacher_name' => $row['teacher_name'] ?? 'Unknown',
                    'teacher' => (object) [
                        'user' => (object) [
                            'name' => $row['teacher_name'] ?? 'Unknown',
                        ],
                    ],
                    'sessions' => (int) ($row['session_count'] ?? 0),
                    'weekly_load' => (float) ($row['weekly_load'] ?? 0),
                    'status' => $row['status'] ?? 'unknown',
                    'source' => collect($row['entries'] ?? [])->pluck('source')->filter()->contains('canonical_pmc_official_sessions')
                        ? 'canonical_pmc_official_sessions'
                        : 'legacy_timetable_entries',
                ]);
            }
        }

        return $rows
            ->sortByDesc('sessions')
            ->take(8)
            ->values();
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

        $canonicalItems = AcademicPmcTimetableGenerationItem::with([
                'subject',
                'courseGroup.subject',
                'courseGroup.program',
                'courseGroup.batch',
                'teacher.user',
                'classroom',
                'slot',
                'batch',
                'timetableVersion',
            ])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'))
            ->where(function ($query) use ($programIds) {
                $query->whereIn('program_id', $programIds)
                    ->orWhereHas('courseGroup', fn ($group) => $group->whereIn('program_id', $programIds));
            })
            ->get();

        $canonicalProgramTermKeys = $canonicalItems
            ->map(fn (AcademicPmcTimetableGenerationItem $item) => $this->programTermKey(
                $item->program_id ?? $item->courseGroup?->program_id,
                $item->term_id ?? $item->courseGroup?->term_id
            ))
            ->unique()
            ->values();

        $legacyRows = TimetableEntry::whereHas('subject', fn ($q) => $q->whereIn('program_id', $programIds))
            ->where(fn ($query) => $this->publishedTimetableScope($query))
            ->with(['subject', 'teacher.user', 'classroom', 'slot', 'batch'])
            ->get()
            ->reject(fn (TimetableEntry $entry) => $canonicalProgramTermKeys->contains($this->programTermKey($entry->program_id, $entry->term_id)));

        $entries = $canonicalItems
            ->map(fn (AcademicPmcTimetableGenerationItem $item) => $this->displayTimetableRowFromCanonicalItem($item))
            ->merge($legacyRows->map(fn (TimetableEntry $entry) => $this->displayTimetableRowFromLegacyEntry($entry)))
            ->sortBy([
                ['day_number', 'asc'],
                ['slot_sort', 'asc'],
                ['subject_name', 'asc'],
                ['group_name', 'asc'],
            ])
            ->groupBy('day_name');

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return view('departmental.program-chair.timetable', compact('entries', 'days'));
    }

    private function displayTimetableRowFromCanonicalItem(AcademicPmcTimetableGenerationItem $item): object
    {
        $subject = $item->subject ?: $item->courseGroup?->subject;
        $batch = $item->batch ?: $item->courseGroup?->batch;

        return (object) [
            'source' => 'canonical_pmc_official_session',
            'day_number' => (int) $item->day_of_week,
            'day_name' => $this->dayName((int) $item->day_of_week),
            'slot_sort' => (int) ($item->slot?->sort_order ?? $item->timetable_slot_id ?? 0),
            'start_time' => $item->slot?->start_time,
            'end_time' => $item->slot?->end_time,
            'subject_name' => $subject?->name ?? 'Subject not linked',
            'subject_code' => $subject?->code,
            'teacher_name' => $item->teacher?->user?->name ?? 'Faculty not assigned',
            'room_name' => $item->classroom?->name ?? 'Room not assigned',
            'batch_name' => $batch?->name,
            'group_name' => $item->courseGroup?->name,
            'session_type' => $item->session_type,
            'duration_slots' => max(1, (int) ($item->duration_slots ?? 1)),
        ];
    }

    private function displayTimetableRowFromLegacyEntry(TimetableEntry $entry): object
    {
        return (object) [
            'source' => 'legacy_timetable_entry',
            'day_number' => is_numeric($entry->day_of_week) ? (int) $entry->day_of_week : array_search((string) $entry->day_of_week, $this->dayMap(), true),
            'day_name' => is_numeric($entry->day_of_week) ? $this->dayName((int) $entry->day_of_week) : (string) $entry->day_of_week,
            'slot_sort' => (int) ($entry->slot?->sort_order ?? $entry->timetable_slot_id ?? 0),
            'start_time' => $entry->slot?->start_time,
            'end_time' => $entry->slot?->end_time,
            'subject_name' => $entry->subject?->name ?? 'Subject not linked',
            'subject_code' => $entry->subject?->code,
            'teacher_name' => $entry->teacher?->user?->name ?? 'Faculty not assigned',
            'room_name' => $entry->classroom?->name ?? 'Room not assigned',
            'batch_name' => $entry->batch?->name,
            'group_name' => null,
            'session_type' => null,
            'duration_slots' => 1,
        ];
    }

    private function dayName(int $day): string
    {
        return $this->dayMap()[$day] ?? 'Day ' . $day;
    }

    private function dayMap(): array
    {
        return [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
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
        $programIds = $this->getAssignedProgramIds();

        $query = $this->pendingProgramChairApprovals($programIds)
            ->with(['approvable' => function ($q) {
                $q->with(['user', 'program', 'batch']);
            }])
            ->latest();

        $approvals = $query->paginate(20)->withQueryString();

        return view('departmental.program-chair.approvals.index', compact('approvals'));
    }

    private function pendingProgramChairApprovals(array $programIds)
    {
        return ApprovalWorkflow::where('approver_role', 'program_chair')
            ->where('status', 'pending')
            ->whereHasMorph('approvable', [Applicant::class], fn($q) => $q->whereIn('program_id', $programIds));
    }

    private function officialAttendanceQuery()
    {
        return Attendance::query()
            ->whereHas('timetableEntry', fn ($query) => $this->publishedTimetableScope($query));
    }

    private function publishedTimetableScope($query)
    {
        return $query
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($versionQuery) {
                $versionQuery->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn ($version) => $version->where('status', 'published'));
            });
    }

    private function publishedTimetableJoinScope($query)
    {
        return $query
            ->where('timetable_entries.is_active', true)
            ->where('timetable_entries.status', 'published')
            ->where(function ($versionQuery) {
                $versionQuery->whereNull('timetable_entries.timetable_version_id')
                    ->orWhereExists(function ($exists) {
                        $exists->selectRaw('1')
                            ->from('timetable_versions')
                            ->whereColumn('timetable_versions.id', 'timetable_entries.timetable_version_id')
                            ->where('timetable_versions.status', 'published');
                    });
            });
    }

    private function authorizeProgramApproval(ApprovalWorkflow $approval): void
    {
        abort_unless($approval->approver_role === 'program_chair', 403);
        abort_unless($approval->status === 'pending', 403);

        $approvable = $approval->approvable;
        abort_unless($approvable instanceof Applicant, 403);
        abort_unless(in_array($approvable->program_id, $this->getAssignedProgramIds(), true), 403);
    }

    public function approve(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        // Check seat capacity before approving
        $applicant = $approval->approvable;
        $this->authorizeProgramApproval($approval);
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

        $this->authorizeProgramApproval($approval);

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
                'totalEntries' => $this->officialTimetableSessionCount($selectedProgram->id, $selectedTerm->id),
            ];
        }

        return view('departmental.program-chair.analytics', compact(
            'programs', 'terms', 'selectedProgram', 'selectedTerm', 'dashboardData'
        ));
    }

    private function officialTimetableSessionCount(int $programId, int $termId): int
    {
        $canonicalCount = AcademicPmcTimetableGenerationItem::where('program_id', $programId)
            ->where('term_id', $termId)
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'))
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->count();

        if ($canonicalCount > 0) {
            return $canonicalCount;
        }

        return TimetableEntry::where('program_id', $programId)
            ->where('term_id', $termId)
            ->where(fn ($query) => $this->publishedTimetableScope($query))
            ->count();
    }

    private function programTermKey(mixed $programId, mixed $termId): string
    {
        return ((string) ($programId ?? 'none')) . ':' . ((string) ($termId ?? 'none'));
    }
}
