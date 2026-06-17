<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{ApprovalWorkflow, Applicant, Program, Department, Teacher, Student, Subject, Exam, ExamResult, Attendance, LeaveApplication, TimetableEntry};
use Illuminate\Http\Request;

class HodController extends Controller
{
    private function hodTeacher()
    {
        return Teacher::where('user_id', auth()->id())->first();
    }

    private function hodDepartmentId(?Teacher $teacher): ?int
    {
        if ($teacher) {
            return $teacher->department_id;
        }

        return auth()->user()->hasRole('hod') ? -1 : null;
    }

    public function dashboard()
    {
        $teacher    = $this->hodTeacher();
        $department = $teacher ? Department::find($teacher->department_id) : null;
        $departmentId = $this->hodDepartmentId($teacher);

        $facultyCount = Teacher::when($departmentId !== null, fn($q) => $q->where('department_id', $departmentId))
            ->where('status', 'active')->count();

        $subjectCount = Subject::when($departmentId !== null, fn($q) => $q->where('department_id', $departmentId))->count();

        $pendingApprovals = $this->pendingHodApprovals($departmentId)->count();

        $pendingLeaves = LeaveApplication::where('status', 'pending')
            ->when($departmentId !== null, fn($q) => $q->whereHas('student.program', fn($p) => $p->where('department_id', $departmentId)))
            ->count();

        $studentCount = Student::when($departmentId !== null, fn($q) => $q->whereHas('program', fn($p) => $p->where('department_id', $departmentId)))
            ->where('status', 'active')->count();

        $attQuery = Attendance::where('date', '>=', now()->subDays(30));
        if ($departmentId !== null) {
            $attQuery->whereHas('timetableEntry.subject', fn($q) => $q->where('department_id', $departmentId));
        }
        $totalAtt   = $attQuery->count();
        $presentAtt = (clone $attQuery)->where('status', 'present')->count();
        $attendancePct = $totalAtt > 0 ? round(($presentAtt / $totalAtt) * 100, 1) : 0;

        $recentExams = Exam::when($departmentId !== null, fn($q) => $q->whereHas('subject', fn($sq) => $sq->where('department_id', $departmentId)))
            ->with(['subject', 'program', 'results'])->latest('exam_date')->take(5)->get()
            ->map(function ($exam) {
                $results = $exam->results;
                $exam->result_count = $results->count();
                $exam->avg_marks    = $results->count() > 0 ? round($results->avg('marks_obtained'), 1) : null;
                $exam->pass_count   = $results->where('marks_obtained', '>=', $exam->passing_marks ?? 40)->count();
                return $exam;
            });

        $faculty = Teacher::when($departmentId !== null, fn($q) => $q->where('department_id', $departmentId))
            ->with('user')->where('status', 'active')->take(10)->get();

        $hodPriority = $this->hodPriority($departmentId, $pendingApprovals, $pendingLeaves, $attendancePct, $facultyCount, $subjectCount);

        return view('departmental.hod.dashboard', compact(
            'department', 'facultyCount', 'subjectCount', 'pendingApprovals',
            'pendingLeaves', 'studentCount', 'attendancePct', 'recentExams', 'faculty', 'hodPriority'
        ));
    }

    private function hodPriority(?int $departmentId, int $pendingApprovals, int $pendingLeaves, float $attendancePct, int $facultyCount, int $subjectCount): array
    {
        if ($departmentId === -1) {
            return [
                'level' => 'warning',
                'title' => 'Department profile needed',
                'body' => 'Your HOD account is not linked to a teacher department profile. Ask an administrator to attach your teacher profile before reviewing approvals, leaves, faculty, or students.',
                'route' => route('hod.dashboard'),
                'action' => 'Contact Admin',
            ];
        }

        if ($pendingApprovals > 0) {
            return [
                'level' => 'warning',
                'title' => "Review {$pendingApprovals} department approval" . ($pendingApprovals === 1 ? '' : 's'),
                'body' => 'Admission approvals should be cleared before candidates move to dean clearance and offer processing.',
                'route' => route('hod.approvals'),
                'action' => 'Open Approvals',
            ];
        }

        if ($pendingLeaves > 0) {
            return [
                'level' => 'warning',
                'title' => "Review {$pendingLeaves} pending leave request" . ($pendingLeaves === 1 ? '' : 's'),
                'body' => 'Leave decisions affect attendance records, mentoring, and academic follow-up.',
                'route' => route('hod.leaves'),
                'action' => 'Review Leaves',
            ];
        }

        if ($attendancePct > 0 && $attendancePct < 75) {
            return [
                'level' => 'danger',
                'title' => 'Department attendance is below threshold',
                'body' => "Last 30-day attendance is {$attendancePct}%. Review department performance and student interventions.",
                'route' => route('hod.department-performance'),
                'action' => 'Review Performance',
            ];
        }

        if ($facultyCount === 0 || $subjectCount === 0) {
            return [
                'level' => 'info',
                'title' => 'Review department setup',
                'body' => 'Faculty or subject records are missing for this department. Check roster and academic setup before term operations.',
                'route' => route('hod.faculty.roster'),
                'action' => 'Review Roster',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No urgent HOD action today',
            'body' => 'Use this time to review department performance, faculty workload, grievances, and upcoming academic risks.',
            'route' => route('hod.department-performance'),
            'action' => 'View Performance',
        ];
    }

    public function approvals(Request $request)
    {
        $teacher = $this->hodTeacher();
        $departmentId = $this->hodDepartmentId($teacher);

        $query = $this->pendingHodApprovals($departmentId)
            ->with(['approvable', 'approver'])
            ->latest();

        $approvals = $query->paginate(20)->withQueryString();
        $approvals->getCollection()->each(function ($approval) {
            if ($approval->approvable instanceof Applicant) {
                $approval->approvable->load(['user', 'program', 'batch']);
            }
        });

        $programs = Program::where('is_active', true)
            ->when($departmentId !== null, fn($q) => $q->where('department_id', $departmentId))
            ->orderBy('name')->get();
        return view('departmental.hod.approvals.index', compact('approvals', 'programs'));
    }

    private function pendingHodApprovals(?int $departmentId)
    {
        return ApprovalWorkflow::where('approver_role', 'hod')
            ->where('status', 'pending')
            ->when($departmentId !== null, function ($query) use ($departmentId) {
                $query->whereHasMorph('approvable', [Applicant::class], fn($q) => $q->whereHas('program', fn($p) => $p->where('department_id', $departmentId)));
            });
    }

    private function authorizeDepartmentApproval(ApprovalWorkflow $approval): void
    {
        abort_unless($approval->approver_role === 'hod', 403);
        abort_unless($approval->status === 'pending', 403);

        $approvable = $approval->approvable;
        $departmentId = $this->hodDepartmentId($this->hodTeacher());

        abort_unless($approvable instanceof Applicant, 403);
        abort_unless($departmentId === null || (int) $approvable->program?->department_id === $departmentId, 403);
    }

    public function approve(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate(['remarks' => 'nullable|string|max:500']);
        $this->authorizeDepartmentApproval($approval);
        $approval->update(['status' => 'approved', 'approver_id' => auth()->id(), 'remarks' => $request->remarks, 'approved_at' => now()]);
        $name = $approval->approvable instanceof Applicant ? ($approval->approvable->user->name ?? 'applicant') : 'applicant';
        return back()->with('success', "Approval granted for {$name}.");
    }

    public function reject(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate(['rejection_reason' => 'required|string|max:500']);
        $this->authorizeDepartmentApproval($approval);
        $approval->update(['status' => 'rejected', 'approver_id' => auth()->id(), 'remarks' => $request->rejection_reason, 'approved_at' => now()]);
        $name = $approval->approvable instanceof Applicant ? ($approval->approvable->user->name ?? 'applicant') : 'applicant';
        return back()->with('error', "Approval rejected for {$name}.");
    }

    public function facultyRoster(Request $request)
    {
        $teacher    = $this->hodTeacher();
        $department = $teacher ? Department::find($teacher->department_id) : null;

        $query = Teacher::with(['user', 'department'])
            ->when($teacher, fn($q) => $q->where('department_id', $teacher->department_id));

        if ($request->filled('status')) $query->where('status', $request->status);

        $workloadData = \App\Models\TimetableEntry::selectRaw('teacher_id, COUNT(DISTINCT subject_id) as subject_count, COUNT(*) as weekly_hours')
            ->where('is_active', true)
            ->groupBy('teacher_id')
            ->get()
            ->keyBy('teacher_id');

        $faculty = $query->get()->map(function ($t) use ($workloadData) {
            $w = $workloadData->get($t->id);
            $t->subject_count = $w?->subject_count ?? 0;
            $t->weekly_hours  = $w?->weekly_hours ?? 0;
            return $t;
        });

        return view('departmental.hod.faculty.roster', compact('faculty', 'department'));
    }

    public function facultyWorkload(Request $request)
    {
        $teacher    = $this->hodTeacher();
        $department = $teacher ? Department::find($teacher->department_id) : null;

        $faculty = Teacher::with(['user', 'timetableEntries.subject'])
            ->when($teacher, fn($q) => $q->where('department_id', $teacher->department_id))
            ->where('status', 'active')->get()
            ->map(function ($t) {
                $entries = $t->timetableEntries->where('is_active', true);
                $t->weekly_slots    = $entries->count();
                $t->subject_count   = $entries->pluck('subject_id')->unique()->count();
                $t->subjects        = $entries->pluck('subject.name')->unique()->values();
                return $t;
            });

        return view('departmental.hod.faculty.workload', compact('faculty', 'department'));
    }

    public function leaves(Request $request)
    {
        $departmentId = $this->hodDepartmentId($this->hodTeacher());
        $query = LeaveApplication::with(['student.user'])->latest();

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($departmentId !== null) {
            $query->whereHas('student.program', fn($p) => $p->where('department_id', $departmentId));
        }

        $leaves = $query->paginate(25)->withQueryString();
        return view('departmental.hod.leaves', compact('leaves'));
    }

    public function reviewLeave(Request $request, LeaveApplication $leave)
    {
        $request->validate([
            'action'  => 'required|in:approved,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $departmentId = $this->hodDepartmentId($this->hodTeacher());
        abort_unless($departmentId === null || (int) $leave->student?->program?->department_id === $departmentId, 403);

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Only pending leave applications can be reviewed.');
        }

        $leave->update([
            'status'         => $request->action,
            'reviewed_by'    => auth()->id(),
            'review_remarks' => $request->remarks,
            'reviewed_at'    => now(),
        ]);

        return back()->with('success', 'Leave application ' . $request->action . '.');
    }

    public function departmentPerformance()
    {
        $teacher    = $this->hodTeacher();
        $department = $teacher ? Department::find($teacher->department_id) : null;

        $subjectIds = Subject::when($teacher, fn($q) => $q->where('department_id', $teacher->department_id))
            ->pluck('id');

        // All exam IDs grouped by subject
        $examsBySubject = Exam::whereIn('subject_id', $subjectIds)
            ->get(['id', 'subject_id', 'passing_marks'])
            ->groupBy('subject_id');

        $allExamIds = $examsBySubject->flatten()->pluck('id');

        // All results for those exams
        $resultsByExam = ExamResult::whereIn('exam_id', $allExamIds)
            ->get(['exam_id', 'marks_obtained', 'is_absent'])
            ->groupBy('exam_id');

        // Attendance aggregated per subject
        $attBySubject = Attendance::join('timetable_entries', 'attendances.timetable_entry_id', '=', 'timetable_entries.id')
            ->whereIn('timetable_entries.subject_id', $subjectIds)
            ->where('date', '>=', now()->subDays(30))
            ->selectRaw('timetable_entries.subject_id, COUNT(*) as total, SUM(CASE WHEN attendances.status="present" THEN 1 ELSE 0 END) as present_count')
            ->groupBy('timetable_entries.subject_id')
            ->get()
            ->keyBy('subject_id');

        $subjects = Subject::when($teacher, fn($q) => $q->where('department_id', $teacher->department_id))
            ->with('program')->get()->map(function ($subject) use ($examsBySubject, $resultsByExam, $attBySubject) {
                $exams = $examsBySubject->get($subject->id, collect());
                $examIds = $exams->pluck('id');
                $results = $examIds->flatMap(fn($id) => $resultsByExam->get($id, collect()));
                $subject->exam_count    = $exams->count();
                $subject->result_count  = $results->count();
                $subject->avg_marks     = $results->count() ? round($results->avg('marks_obtained'), 1) : null;
                $subject->pass_rate     = $results->count()
                    ? round($results->where('marks_obtained', '>=', 40)->count() / $results->count() * 100, 1)
                    : null;

                $att = $attBySubject->get($subject->id);
                $total   = $att?->total ?? 0;
                $present = $att?->present_count ?? 0;
                $subject->attendance_pct = $total > 0 ? round($present / $total * 100, 1) : null;
                return $subject;
            });

        $totalStudents = Student::when($teacher, fn($q) => $q->whereHas('program', fn($p) => $p->where('department_id', $teacher->department_id)))
            ->where('status', 'active')->count();

        return view('departmental.hod.department-performance', compact('subjects', 'department', 'totalStudents'));
    }
}
