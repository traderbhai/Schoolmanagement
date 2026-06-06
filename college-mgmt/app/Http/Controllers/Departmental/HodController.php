<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{ApprovalWorkflow, Applicant, Program, Department, Teacher, Student, Subject, Exam, ExamResult, Attendance, LeaveApplication};
use Illuminate\Http\Request;

class HodController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();

        // Get HOD's department (first department where a teacher record exists for this user)
        $teacher = Teacher::where('user_id', $user->id)->first();
        $department = $teacher ? Department::find($teacher->department_id) : null;

        // Faculty in department
        $facultyCount = $teacher
            ? Teacher::where('department_id', $teacher->department_id)->where('status', 'active')->count()
            : Teacher::where('status', 'active')->count();

        // Subjects in department
        $subjectCount = $teacher
            ? Subject::where('department_id', $teacher->department_id)->count()
            : Subject::count();

        // Pending approvals for HOD
        $pendingApprovals = ApprovalWorkflow::where('approver_role', 'hod')->where('status', 'pending')->count();

        // Pending leave applications
        $pendingLeaves = 0;
        try {
            $pendingLeaves = LeaveApplication::where('status', 'pending')->count();
        } catch (\Exception $e) {
            $pendingLeaves = 0;
        }

        // Students in programs under this department
        $studentCount = $teacher
            ? Student::whereHas('program', fn($q) => $q->where('department_id', $teacher->department_id))->where('status', 'active')->count()
            : Student::where('status', 'active')->count();

        // Department attendance % (last 30 days)
        $attQuery = Attendance::where('date', '>=', now()->subDays(30));
        if ($teacher) {
            $attQuery->whereHas('subject', fn($q) => $q->where('department_id', $teacher->department_id));
        }
        $totalAtt = $attQuery->count();
        $presentAtt = (clone $attQuery)->where('status', 'present')->count();
        $attendancePct = $totalAtt > 0 ? round(($presentAtt / $totalAtt) * 100, 1) : 0;

        // Recent exam results for department subjects
        $recentExams = Exam::when($teacher, fn($q) => $q->whereHas('subject', fn($sq) => $sq->where('department_id', $teacher->department_id)))
            ->with(['subject', 'program'])
            ->latest('exam_date')
            ->take(5)
            ->get()
            ->map(function ($exam) {
                $results = ExamResult::where('exam_id', $exam->id)->get();
                $exam->result_count = $results->count();
                $exam->avg_marks = $results->count() > 0 ? round($results->avg('marks_obtained'), 1) : null;
                $exam->pass_count = $results->where('marks_obtained', '>=', $exam->passing_marks ?? 40)->count();
                return $exam;
            });

        // Faculty list for department
        $faculty = Teacher::where('department_id', $teacher?->department_id)
            ->with('user')
            ->where('status', 'active')
            ->take(10)
            ->get();

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

        if ($request->filled('program_id')) {
            $query->whereHasMorph('approvable', [Applicant::class], function ($q) use ($request) {
                $q->where('program_id', $request->program_id);
            });
        }

        $approvals = $query->paginate(20)->withQueryString();

        // Eager-load nested relations on the already-loaded approvable instances
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
        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $approval->update([
            'status'      => 'approved',
            'approver_id' => auth()->id(),
            'remarks'     => $request->remarks,
            'approved_at' => now(),
        ]);

        $applicantName = $approval->approvable instanceof Applicant
            ? ($approval->approvable->user->name ?? 'applicant')
            : 'applicant';

        return back()->with('success', "Approval granted for {$applicantName}.");
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

        $applicantName = $approval->approvable instanceof Applicant
            ? ($approval->approvable->user->name ?? 'applicant')
            : 'applicant';

        return back()->with('error', "Approval rejected for {$applicantName}.");
    }
}
