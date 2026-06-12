<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Program, Student, Subject, Exam, ExamResult, Attendance, Batch, Term, RoleProgramAssignment, TimetableEntry, ApprovalWorkflow, Applicant, SeatMatrix,
    TimetableVersion, ElectiveRegistrationWindow, LeaveApplication, AttendanceCondonation, StudentGrievance};
use App\Helpers\AccessControl;
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

        $approval->update([
            'status'      => 'approved',
            'approver_id' => auth()->id(),
            'remarks'     => $request->remarks,
            'approved_at' => now(),
        ]);

        // If approvable is an Applicant, check seat capacity and finalize
        if ($approval->approvable instanceof Applicant) {
            $applicant  = $approval->approvable;
            $seatMatrix = SeatMatrix::where('program_id', $applicant->program_id)->first();

            if ($seatMatrix) {
                $filledSeats = Applicant::where('program_id', $applicant->program_id)
                    ->whereIn('status', ['offer_accepted', 'enrolled'])
                    ->count();

                if ($filledSeats >= $seatMatrix->total_seats) {
                    // Revert — over capacity
                    $approval->update(['status' => 'pending', 'approver_id' => null, 'approved_at' => null]);
                    return back()->with('error', 'Program capacity is full. Cannot approve additional applicants.');
                }
            }

            // Create offer letter if not already issued
            if (!$applicant->offerLetter) {
                \App\Models\OfferLetter::create([
                    'applicant_id'        => $applicant->id,
                    'program_id'          => $applicant->program_id,
                    'batch_id'            => $applicant->batch_id,
                    'status'              => 'issued',
                    'issued_at'           => now(),
                    'issued_by'           => auth()->id(),
                    'acceptance_deadline' => now()->addDays(14)->toDateString(),
                ]);
            }

            // Update applicant status
            $applicant->update(['status' => 'selected']);
        }

        return back()->with('success', 'Approval granted successfully.');
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
