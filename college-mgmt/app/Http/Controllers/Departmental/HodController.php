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

    public function dashboard()
    {
        $teacher    = $this->hodTeacher();
        $department = $teacher ? Department::find($teacher->department_id) : null;

        $facultyCount = Teacher::when($teacher, fn($q) => $q->where('department_id', $teacher->department_id))
            ->where('status', 'active')->count();

        $subjectCount = Subject::when($teacher, fn($q) => $q->where('department_id', $teacher->department_id))->count();

        $pendingApprovals = ApprovalWorkflow::where('approver_role', 'hod')->where('status', 'pending')->count();

        $pendingLeaves = LeaveApplication::where('status', 'pending')->count();

        $studentCount = Student::when($teacher, fn($q) => $q->whereHas('program', fn($p) => $p->where('department_id', $teacher->department_id)))
            ->where('status', 'active')->count();

        $attQuery = Attendance::where('date', '>=', now()->subDays(30));
        if ($teacher) $attQuery->whereHas('subject', fn($q) => $q->where('department_id', $teacher->department_id));
        $totalAtt   = $attQuery->count();
        $presentAtt = (clone $attQuery)->where('status', 'present')->count();
        $attendancePct = $totalAtt > 0 ? round(($presentAtt / $totalAtt) * 100, 1) : 0;

        $recentExams = Exam::when($teacher, fn($q) => $q->whereHas('subject', fn($sq) => $sq->where('department_id', $teacher->department_id)))
            ->with(['subject', 'program'])->latest('exam_date')->take(5)->get()
            ->map(function ($exam) {
                $results = ExamResult::where('exam_id', $exam->id)->get();
                $exam->result_count = $results->count();
                $exam->avg_marks    = $results->count() > 0 ? round($results->avg('marks_obtained'), 1) : null;
                $exam->pass_count   = $results->where('marks_obtained', '>=', $exam->passing_marks ?? 40)->count();
                return $exam;
            });

        $faculty = Teacher::when($teacher, fn($q) => $q->where('department_id', $teacher->department_id))
            ->with('user')->where('status', 'active')->take(10)->get();

        return view('departmental.hod.dashboard', compact(
            'department', 'facultyCount', 'subjectCount', 'pendingApprovals',
            'pendingLeaves', 'studentCount', 'attendancePct', 'recentExams', 'faculty'
        ));
    }

    public function approvals(Request $request)
    {
        $query = ApprovalWorkflow::where('approver_role', 'hod')
            ->where('status', 'pending')
            ->with(['approvable', 'approver'])
            ->latest();

        $approvals = $query->paginate(20)->withQueryString();
        $approvals->getCollection()->each(function ($approval) {
            if ($approval->approvable instanceof Applicant) {
                $approval->approvable->load(['user', 'program', 'batch']);
            }
        });

        $programs = Program::where('is_active', true)->orderBy('name')->get();
        return view('departmental.hod.approvals.index', compact('approvals', 'programs'));
    }

    public function approve(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate(['remarks' => 'nullable|string|max:500']);
        $approval->update(['status' => 'approved', 'approver_id' => auth()->id(), 'remarks' => $request->remarks, 'approved_at' => now()]);
        $name = $approval->approvable instanceof Applicant ? ($approval->approvable->user->name ?? 'applicant') : 'applicant';
        return back()->with('success', "Approval granted for {$name}.");
    }

    public function reject(Request $request, ApprovalWorkflow $approval)
    {
        $request->validate(['rejection_reason' => 'required|string|max:500']);
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

        $faculty = $query->get()->map(function ($t) {
            $t->subject_count = TimetableEntry::where('teacher_id', $t->id)->where('is_active', true)
                ->distinct('subject_id')->count('subject_id');
            $t->weekly_hours  = TimetableEntry::where('teacher_id', $t->id)->where('is_active', true)->count();
            return $t;
        });

        return view('departmental.hod.faculty.roster', compact('faculty', 'department'));
    }

    public function facultyWorkload(Request $request)
    {
        $teacher    = $this->hodTeacher();
        $department = $teacher ? Department::find($teacher->department_id) : null;

        $faculty = Teacher::with(['user'])
            ->when($teacher, fn($q) => $q->where('department_id', $teacher->department_id))
            ->where('status', 'active')->get()
            ->map(function ($t) {
                $entries = TimetableEntry::where('teacher_id', $t->id)->where('is_active', true)
                    ->with('subject')->get();
                $t->weekly_slots    = $entries->count();
                $t->subject_count   = $entries->pluck('subject_id')->unique()->count();
                $t->subjects        = $entries->pluck('subject.name')->unique()->values();
                return $t;
            });

        return view('departmental.hod.faculty.workload', compact('faculty', 'department'));
    }

    public function leaves(Request $request)
    {
        $query = LeaveApplication::with(['student.user'])->latest();

        if ($request->filled('status')) $query->where('status', $request->status);

        $leaves = $query->paginate(25)->withQueryString();
        return view('departmental.hod.leaves', compact('leaves'));
    }

    public function reviewLeave(Request $request, LeaveApplication $leave)
    {
        $request->validate([
            'action'  => 'required|in:approved,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

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

        $subjects = Subject::when($teacher, fn($q) => $q->where('department_id', $teacher->department_id))
            ->with('program')->get()->map(function ($subject) {
                $exams = Exam::where('subject_id', $subject->id)->pluck('id');
                $results = ExamResult::whereIn('exam_id', $exams)->get();
                $subject->exam_count    = $exams->count();
                $subject->result_count  = $results->count();
                $subject->avg_marks     = $results->count() ? round($results->avg('marks_obtained'), 1) : null;
                $subject->pass_rate     = $results->count()
                    ? round($results->where('marks_obtained', '>=', 40)->count() / $results->count() * 100, 1)
                    : null;

                $att = Attendance::where('subject_id', $subject->id)->where('date', '>=', now()->subDays(30));
                $total   = $att->count();
                $present = (clone $att)->where('status', 'present')->count();
                $subject->attendance_pct = $total > 0 ? round($present / $total * 100, 1) : null;
                return $subject;
            });

        $totalStudents = Student::when($teacher, fn($q) => $q->whereHas('program', fn($p) => $p->where('department_id', $teacher->department_id)))
            ->where('status', 'active')->count();

        return view('departmental.hod.department-performance', compact('subjects', 'department', 'totalStudents'));
    }
}
