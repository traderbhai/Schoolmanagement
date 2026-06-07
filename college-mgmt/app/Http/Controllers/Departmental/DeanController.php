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

        // At-risk students: attendance < 75% (last 30 days)
        $atRiskStudents = Student::where('status', 'active')
            ->with(['user', 'program'])
            ->get()
            ->map(function($student) {
                $total = Attendance::where('student_id', $student->id)
                    ->where('date', '>=', now()->subDays(30))->count();
                $present = Attendance::where('student_id', $student->id)
                    ->where('date', '>=', now()->subDays(30))
                    ->where('status', 'present')->count();
                $pct = $total > 0 ? round(($present / $total) * 100, 1) : null;
                $student->attendance_pct = $pct;
                return $student;
            })
            ->filter(fn($s) => $s->attendance_pct !== null && $s->attendance_pct < 75)
            ->sortBy('attendance_pct')
            ->take(8);

        // Program health: pass rate per program
        $programs = Program::where('is_active', true)
            ->withCount(['students' => fn($q) => $q->where('status', 'active'), 'batches'])
            ->get()
            ->map(function($prog) {
                $results = ExamResult::whereHas('exam', fn($q) => $q->where('program_id', $prog->id))->get();
                $total = $results->count();
                $passed = 0;
                if ($total > 0) {
                    $passed = $results->filter(function($r) {
                        $exam = \App\Models\Exam::find($r->exam_id);
                        return $exam && $r->marks_obtained >= ($exam->passing_marks ?? 40);
                    })->count();
                }
                $prog->pass_rate = $total > 0 ? round(($passed / $total) * 100, 1) : null;
                $prog->faculty_count = Teacher::where('department_id', $prog->department_id)->where('status', 'active')->count();
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

        return view('departmental.dean.dashboard', compact(
            'totalPrograms', 'totalStudents', 'totalFaculty',
            'totalExams', 'attendancePct', 'programs', 'recentResults',
            'pendingApprovals', 'atRiskStudents', 'recentApprovals',
            'openGrievances', 'overdueApprovals'
        ));
    }

    public function programs()
    {
        $programs = Program::where('is_active', true)
            ->with(['department', 'batches'])
            ->withCount([
                'students' => fn($q) => $q->where('status', 'active'),
                'batches',
                'subjects',
            ])
            ->get()
            ->map(function ($prog) {
                $prog->faculty_count = Teacher::where('department_id', $prog->department_id)
                    ->where('status', 'active')->count();
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
        // Top performers by exam results avg
        $topPerformers = Student::with(['user', 'program'])
            ->where('status', 'active')
            ->withCount('examResults')
            ->get()
            ->filter(fn($s) => $s->exam_results_count > 0)
            ->map(function ($s) {
                $s->avg_marks = $s->examResults()->avg('marks_obtained') ?? 0;
                return $s;
            })
            ->sortByDesc('avg_marks')
            ->take(10);

        // At-risk: students with avg marks < 40% of possible
        $atRisk = Student::with(['user', 'program'])
            ->where('status', 'active')
            ->get()
            ->map(function ($s) {
                $results = $s->examResults()->with('exam')->get();
                if ($results->isEmpty()) return null;
                $pct = $results->avg(fn($r) => $r->exam ? ($r->marks_obtained / max($r->exam->total_marks, 1)) * 100 : 0);
                $s->score_pct = round($pct, 1);
                return $s;
            })
            ->filter(fn($s) => $s && $s->score_pct < 40)
            ->sortBy('score_pct')
            ->take(20);

        // Program-wise pass rate
        $programs = Program::where('is_active', true)->withCount('students')->get()->map(function ($p) {
            $results = ExamResult::whereHas('exam', fn($q) => $q->where('program_id', $p->id))->get();
            $total = $results->count();
            $passed = $results->filter(fn($r) => !$r->is_absent && $r->exam && $r->marks_obtained >= $r->exam->passing_marks)->count();
            $p->pass_rate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
            return $p;
        });

        return view('departmental.dean.academics', compact('topPerformers', 'atRisk', 'programs'));
    }

    public function attendance()
    {
        $programs = Program::where('is_active', true)->get()->map(function ($p) {
            $studentIds = Student::where('program_id', $p->id)->where('status', 'active')->pluck('id');
            $total   = Attendance::whereIn('student_id', $studentIds)->count();
            $present = Attendance::whereIn('student_id', $studentIds)->where('status', 'present')->count();
            $p->att_pct  = $total > 0 ? round(($present / $total) * 100, 1) : 0;
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

            // Create next approval chain for Program Chair
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
