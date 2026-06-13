<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Program, Student, Teacher, Exam, ExamResult, Attendance, Batch, Term, ApprovalWorkflow, Applicant, OfferLetter};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeanController extends Controller
{
    public function dashboard()
    {
        $totalPrograms   = Program::where('is_active', true)->count();
        $totalStudents   = Student::where('status', 'active')->count();
        $totalFaculty    = Teacher::where('status', 'active')->count();
        $totalExams      = Exam::whereYear('exam_date', now()->year)->count();

        // Attendance %
        $totalAtt   = Attendance::count();
        $presentAtt = Attendance::where('status', 'present')->count();
        $attendancePct = $totalAtt > 0 ? round(($presentAtt / $totalAtt) * 100, 1) : 0;

        // Pending approvals for dean
        $pendingApprovals = ApprovalWorkflow::where('approver_role', 'dean_academics')
            ->where('status', 'pending')->count();

        // At-risk students — single aggregate query instead of 2×N per-student queries
        $attData = Attendance::where('date', '>=', now()->subDays(30))
            ->selectRaw('student_id, COUNT(*) as total, SUM(CASE WHEN status="present" THEN 1 ELSE 0 END) as present_count')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $atRiskStudents = Student::where('status', 'active')
            ->with(['user', 'program'])
            ->get()
            ->map(function ($student) use ($attData) {
                $att = $attData->get($student->id);
                $total   = $att->total ?? 0;
                $present = $att->present_count ?? 0;
                $student->attendance_pct = $total > 0 ? round(($present / $total) * 100, 1) : null;
                return $student;
            })
            ->filter(fn($s) => $s->attendance_pct !== null && $s->attendance_pct < 75)
            ->sortBy('attendance_pct')
            ->take(8);

        // Program health — pre-aggregate faculty counts + eager-load exam results
        $facultyCounts = Teacher::selectRaw('department_id, COUNT(*) as cnt')
            ->where('status', 'active')
            ->groupBy('department_id')
            ->get()
            ->keyBy('department_id');

        $programs = Program::where('is_active', true)
            ->withCount(['students' => fn($q) => $q->where('status', 'active'), 'batches'])
            ->with(['examResults.exam'])
            ->get()
            ->map(function ($prog) use ($facultyCounts) {
                $results = $prog->examResults;
                $total   = $results->count();
                $passed  = $total > 0
                    ? $results->filter(fn($r) => $r->exam && $r->marks_obtained >= ($r->exam->passing_marks ?? 40))->count()
                    : 0;
                $prog->pass_rate    = $total > 0 ? round(($passed / $total) * 100, 1) : null;
                $prog->faculty_count = $facultyCounts->get($prog->department_id)?->cnt ?? 0;
                return $prog;
            });

        // Recent results
        $recentResults = ExamResult::with(['exam.program', 'student.user'])
            ->latest()->take(8)->get();

        // Recent approvals
        $recentApprovals = ApprovalWorkflow::where('approver_role', 'dean_academics')
            ->with('approvable')
            ->latest()->take(5)->get();

        try {
            $openGrievances = \App\Models\StudentGrievance::whereIn('status', ['open','under_review','escalated'])->count();
        } catch (\Exception $e) { $openGrievances = 0; }

        $overdueApprovals = ApprovalWorkflow::where('status','pending')
            ->whereNotNull('due_at')->where('due_at','<',now())->count();

        // Academic unit overviews from org hierarchy
        $academicOverview = [];
        try {
            $pendingChair  = ApprovalWorkflow::where('approver_role','program_chair')->where('status','pending')->count();
            $pendingLeaves = \App\Models\LeaveApplication::where('status','pending')->count();
            $pendingAppeals = \App\Models\MarksAppeal::where('status','pending')->count();

            $childLines = \App\Models\OrgReportingLine::getChildRoles('dean_academics');
            foreach ($childLines as $line) {
                $summary = match($line->child_role) {
                    'program_chair' => [
                        'label'       => 'Program Chair / PMC',
                        'icon'        => 'bi-diagram-3-fill',
                        'color'       => 'primary',
                        'route'       => 'chair.dashboard',
                        'route_label' => 'View PMC',
                        'can_full'    => $line->can_view_full,
                        'metrics'     => [
                            ['label' => 'Active Students',   'value' => $totalStudents],
                            ['label' => 'Pending Approvals', 'value' => $pendingChair],
                            ['label' => 'Open Grievances',   'value' => $openGrievances],
                        ],
                    ],
                    'hod' => [
                        'label'       => 'Head of Department',
                        'icon'        => 'bi-building',
                        'color'       => 'success',
                        'route'       => 'hod.dashboard',
                        'route_label' => 'View HOD',
                        'can_full'    => $line->can_view_full,
                        'metrics'     => [
                            ['label' => 'Active Faculty',  'value' => $totalFaculty],
                            ['label' => 'Pending Leaves',  'value' => $pendingLeaves],
                        ],
                    ],
                    'exam_cell' => [
                        'label'       => 'Exam Cell',
                        'icon'        => 'bi-file-earmark-check',
                        'color'       => 'warning',
                        'route'       => 'exam-cell.dashboard',
                        'route_label' => 'View Exam Cell',
                        'can_full'    => $line->can_view_full,
                        'metrics'     => [
                            ['label' => 'Exams This Year', 'value' => $totalExams],
                            ['label' => 'Pending Appeals', 'value' => $pendingAppeals],
                        ],
                    ],
                    default => null,
                };
                if ($summary) $academicOverview[] = $summary;
            }
        } catch (\Throwable $e) {}

        return view('departmental.dean.dashboard', compact(
            'totalPrograms', 'totalStudents', 'totalFaculty',
            'totalExams', 'attendancePct', 'programs', 'recentResults',
            'pendingApprovals', 'atRiskStudents', 'recentApprovals',
            'openGrievances', 'overdueApprovals', 'academicOverview'
        ));
    }

    public function programs()
    {
        $facultyCounts = Teacher::selectRaw('department_id, COUNT(*) as cnt')
            ->where('status', 'active')
            ->groupBy('department_id')
            ->get()
            ->keyBy('department_id');

        $programs = Program::where('is_active', true)
            ->with(['department', 'batches'])
            ->withCount([
                'students' => fn($q) => $q->where('status', 'active'),
                'batches',
                'subjects',
            ])
            ->get()
            ->map(function ($prog) use ($facultyCounts) {
                $prog->faculty_count = $facultyCounts->get($prog->department_id)?->cnt ?? 0;
                return $prog;
            });

        return view('departmental.dean.programs', compact('programs'));
    }

    public function students(Request $request)
    {
        $query = Student::with(['user', 'program', 'batch'])
            ->where('status', '!=', 'graduated');

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
                ->orWhere('enrollment_number', 'like', "%{$search}%");
        }

        $students  = $query->paginate(25)->withQueryString();
        $programs  = Program::where('is_active', true)->orderBy('name')->get();
        $batches   = Batch::orderBy('name')->get();

        return view('departmental.dean.students', compact('students', 'programs', 'batches'));
    }

    public function academics()
    {
        // Top performers — eager load exam results, compute avg in PHP
        $topPerformers = Student::with(['user', 'program', 'examResults'])
            ->where('status', 'active')
            ->get()
            ->filter(fn($s) => $s->examResults->count() > 0)
            ->map(function ($s) {
                $s->avg_marks = round($s->examResults->avg('marks_obtained') ?? 0, 1);
                return $s;
            })
            ->sortByDesc('avg_marks')
            ->take(10);

        // At-risk: students with avg marks < 40% — eager load exam results
        $atRisk = Student::with(['user', 'program', 'examResults.exam'])
            ->where('status', 'active')
            ->get()
            ->map(function ($s) {
                $results = $s->examResults;
                if ($results->isEmpty()) return null;
                $pct = $results->avg(fn($r) => $r->exam ? ($r->marks_obtained / max($r->exam->total_marks, 1)) * 100 : 0);
                $s->score_pct = round($pct, 1);
                return $s;
            })
            ->filter(fn($s) => $s && $s->score_pct < 40)
            ->sortBy('score_pct')
            ->take(20);

        // Program-wise pass rate — bulk load results grouped by program
        $programIds = Program::where('is_active', true)->pluck('id');
        $resultsByProgram = ExamResult::with('exam')
            ->whereHas('exam', fn($q) => $q->whereIn('program_id', $programIds))
            ->get()
            ->groupBy(fn($r) => $r->exam?->program_id);

        $programs = Program::where('is_active', true)->withCount('students')->get()->map(function ($p) use ($resultsByProgram) {
            $results = $resultsByProgram->get($p->id, collect());
            $total   = $results->count();
            $passed  = $results->filter(fn($r) => !$r->is_absent && $r->exam && $r->marks_obtained >= ($r->exam->passing_marks ?? 40))->count();
            $p->pass_rate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
            return $p;
        });

        return view('departmental.dean.academics', compact('topPerformers', 'atRisk', 'programs'));
    }

    public function attendance()
    {
        // Single JOIN query instead of 3 queries per program
        $attendanceByProgram = Attendance::selectRaw(
            'students.program_id, COUNT(*) as total, SUM(CASE WHEN attendances.status="present" THEN 1 ELSE 0 END) as present_count'
        )->join('students', 'attendances.student_id', '=', 'students.id')
         ->groupBy('students.program_id')
         ->get()
         ->keyBy('program_id');

        $programs = Program::where('is_active', true)->get()->map(function ($p) use ($attendanceByProgram) {
            $att = $attendanceByProgram->get($p->id);
            $total   = $att->total ?? 0;
            $present = $att->present_count ?? 0;
            $p->att_pct   = $total > 0 ? round(($present / $total) * 100, 1) : 0;
            $p->att_total = $total;
            return $p;
        });

        return view('departmental.dean.attendance', compact('programs'));
    }

    public function approvals(Request $request)
    {
        $query = ApprovalWorkflow::where('approver_role', 'dean_academics')
            ->where('status', 'pending')
            ->with(['approvable', 'approver'])
            ->latest();

        if ($request->filled('program_id')) {
            $query->whereHasMorph('approvable', [Applicant::class], function ($q) use ($request) {
                $q->where('program_id', $request->program_id);
            });
        }

        $approvals = $query->paginate(20)->withQueryString();

        $approvals->getCollection()->each(function ($approval) {
            if ($approval->approvable instanceof Applicant) {
                $approval->approvable->load(['user', 'program', 'batch']);
            }
        });

        $programs = Program::where('is_active', true)->get();
        $approved_count = ApprovalWorkflow::where('approver_role', 'dean_academics')
            ->where('status', 'approved')->count();

        return view('departmental.dean.approvals.index', compact('approvals', 'programs', 'approved_count'));
    }

    public function approve(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $approval->update([
            'status'      => 'approved',
            'approver_id' => auth()->id(),
            'remarks'     => $request->remarks,
            'approved_at' => now(),
        ]);

        // If this is an Applicant, auto-generate OfferLetter
        if ($approval->approvable_type === Applicant::class) {
            $applicant = $approval->approvable;
            if (!$applicant->offerLetter) {
                OfferLetter::create([
                    'applicant_id' => $applicant->id,
                    'program_id'   => $applicant->program_id,
                    'batch_id'     => $applicant->batch_id,
                    'status'       => 'issued',
                    'issued_at'    => now(),
                    'issued_by'    => auth()->id(),
                    'acceptance_deadline' => now()->addDays(14)->toDateString(),
                ]);
            }

            ApprovalWorkflow::create([
                'approvable_type' => Applicant::class,
                'approvable_id'   => $applicant->id,
                'approver_role'   => 'program_chair',
                'status'          => 'pending',
            ]);
        }

        return back()->with('success', 'Approval granted and offer letter generated.');
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
}
