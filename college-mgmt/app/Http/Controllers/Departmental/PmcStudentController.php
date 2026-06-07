<?php
namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Program, Student, Batch, Term, Teacher, LeaveApplication, AttendanceCondonation,
                StudentGrievance, TermPromotion, Attendance, ExamResult, FeeDemand,
                StudentSubjectEnrollment, RoleProgramAssignment, ProgramSubject};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmcStudentController extends Controller {

    private function programIds(): array {
        $ids = RoleProgramAssignment::where('user_id', Auth::id())
            ->where('is_active', true)->pluck('program_id')->toArray();
        return $ids ?: Program::where('is_active', true)->pluck('id')->toArray();
    }

    // ── At-risk student dashboard ─────────────────────────────────────────────
    public function atRisk(Request $request) {
        $programIds = $this->programIds();

        $students = Student::whereIn('program_id', $programIds)
            ->where('status', 'active')
            ->with(['user', 'program', 'batch'])
            ->get();

        $currentTerm = Term::latest('start_date')->first();

        $atRisk = $students->map(function ($student) use ($currentTerm) {
            $risks = [];

            // Attendance risk: any subject < 75%
            $attBySubject = Attendance::where('student_id', $student->id)
                ->selectRaw('subject_id, COUNT(*) as total, SUM(CASE WHEN status="present" THEN 1 ELSE 0 END) as present')
                ->groupBy('subject_id')
                ->get();

            $lowAtt = $attBySubject->filter(fn($r) => $r->total > 0 && ($r->present / $r->total) < 0.75);
            if ($lowAtt->isNotEmpty()) {
                $risks[] = 'attendance';
            }

            // Academic risk: CGPA < 5.0 (approx from results)
            $results = ExamResult::where('student_id', $student->id)->get();
            if ($results->isNotEmpty()) {
                $totalPct = $results->avg(fn($r) => $r->exam ? ($r->marks_obtained / max($r->exam->total_marks ?? 100, 1)) * 100 : null);
                if ($totalPct !== null && $totalPct < 50) {
                    $risks[] = 'academic';
                }
            }

            // Arrear risk
            $arrears = $results->filter(fn($r) => $r->exam &&
                ($r->marks_obtained / max($r->exam->total_marks ?? 100, 1)) * 100 < 35
            );
            if ($arrears->isNotEmpty()) {
                $risks[] = 'arrear';
            }

            // Financial risk
            $dues = FeeDemand::where('student_id', $student->id)
                ->where('status', 'pending')
                ->where('due_date', '<', now()->toDateString())
                ->exists();
            if ($dues) {
                $risks[] = 'financial';
            }

            $student->risks = $risks;
            $student->low_att_count = $lowAtt->count();
            return $student;
        })->filter(fn($s) => !empty($s->risks));

        // Filter by risk type
        if ($request->filled('risk')) {
            $atRisk = $atRisk->filter(fn($s) => in_array($request->risk, $s->risks));
        }

        $batches  = Batch::whereIn('program_id', $programIds)->orderBy('name')->get();
        $programs = Program::whereIn('id', $programIds)->get();

        return view('departmental.program-chair.students.at-risk', compact(
            'atRisk', 'batches', 'programs', 'currentTerm'
        ));
    }

    // ── Mentor assignment ─────────────────────────────────────────────────────
    public function mentors(Request $request) {
        $programIds = $this->programIds();

        $query = Student::whereIn('program_id', $programIds)
            ->where('status', 'active')
            ->with(['user', 'batch', 'mentor.user']);

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        $students = $query->orderBy('id')->paginate(30)->withQueryString();
        $teachers = Teacher::with('user')->where('status', 'active')->orderBy('id')->get();
        $batches  = Batch::whereIn('program_id', $programIds)->orderBy('name')->get();

        return view('departmental.program-chair.students.mentors', compact(
            'students', 'teachers', 'batches'
        ));
    }

    public function assignMentor(Request $request) {
        $request->validate([
            'student_id'  => 'required|exists:students,id',
            'mentor_id'   => 'nullable|exists:teachers,id',
        ]);

        Student::where('id', $request->student_id)->update(['mentor_id' => $request->mentor_id]);
        return back()->with('success', 'Mentor updated.');
    }

    public function bulkAssignMentor(Request $request) {
        $request->validate([
            'batch_id'  => 'required|exists:batches,id',
            'mentor_id' => 'required|exists:teachers,id',
        ]);

        Student::where('batch_id', $request->batch_id)
            ->where('status', 'active')
            ->update(['mentor_id' => $request->mentor_id]);

        return back()->with('success', 'Mentor assigned to entire batch.');
    }

    // ── Leave approvals ───────────────────────────────────────────────────────
    public function leaves(Request $request) {
        $programIds = $this->programIds();

        $query = LeaveApplication::whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))
            ->with(['student.user', 'student.batch'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaves = $query->paginate(25)->withQueryString();

        return view('departmental.program-chair.students.leaves', compact('leaves'));
    }

    public function approveLeave(Request $request, LeaveApplication $leave) {
        $request->validate(['remarks' => 'nullable|string|max:300']);
        $leave->update([
            'status'         => 'approved',
            'reviewed_by'    => Auth::id(),
            'review_remarks' => $request->remarks,
            'reviewed_at'    => now(),
        ]);
        return back()->with('success', 'Leave approved.');
    }

    public function rejectLeave(Request $request, LeaveApplication $leave) {
        $request->validate(['remarks' => 'required|string|max:300']);
        $leave->update([
            'status'         => 'rejected',
            'reviewed_by'    => Auth::id(),
            'review_remarks' => $request->remarks,
            'reviewed_at'    => now(),
        ]);
        return back()->with('success', 'Leave rejected.');
    }

    // ── Condonation review ────────────────────────────────────────────────────
    public function condonations(Request $request) {
        $programIds = $this->programIds();

        $query = AttendanceCondonation::whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))
            ->with(['student.user', 'student.batch', 'subject'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $condonations = $query->paginate(25)->withQueryString();

        return view('departmental.program-chair.students.condonations', compact('condonations'));
    }

    public function approveCondonation(Request $request, AttendanceCondonation $condonation) {
        $request->validate([
            'sessions_condoned' => 'required|integer|min:1',
            'remarks'           => 'nullable|string|max:300',
        ]);
        $condonation->update([
            'status'            => 'approved',
            'sessions_condoned' => $request->sessions_condoned,
            'approved_by'       => Auth::id(),
            'remarks'           => $request->remarks,
        ]);
        return back()->with('success', 'Condonation approved.');
    }

    public function rejectCondonation(Request $request, AttendanceCondonation $condonation) {
        $request->validate(['remarks' => 'required|string|max:300']);
        $condonation->update([
            'status'      => 'rejected',
            'approved_by' => Auth::id(),
            'remarks'     => $request->remarks,
        ]);
        return back()->with('success', 'Condonation rejected.');
    }

    // ── Grievances (program-level) ────────────────────────────────────────────
    public function grievances(Request $request) {
        $programIds = $this->programIds();

        $query = StudentGrievance::whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))
            ->with(['student.user', 'student.batch'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $grievances = $query->paginate(25)->withQueryString();

        return view('departmental.program-chair.students.grievances', compact('grievances'));
    }

    public function updateGrievance(Request $request, StudentGrievance $grievance) {
        $request->validate([
            'status'           => 'required|in:open,under_review,escalated,resolved',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);
        $grievance->update([
            'status'           => $request->status,
            'resolution_notes' => $request->resolution_notes,
            'resolved_at'      => $request->status === 'resolved' ? now() : null,
        ]);
        return back()->with('success', 'Grievance updated.');
    }

    // ── Elective override ─────────────────────────────────────────────────────
    public function electiveOverride(Request $request) {
        $programIds = $this->programIds();
        $currentTerm = Term::latest('start_date')->first();

        // Elective subjects in these programs
        $electiveSubjects = ProgramSubject::whereIn('program_id', $programIds)
            ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
            ->whereIn('type', ['elective','open_elective'])
            ->with('subject')
            ->get();

        $query = StudentSubjectEnrollment::whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))
            ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
            ->where('enrollment_type', 'elective')
            ->with(['student.user', 'student.batch', 'subject'])
            ->orderBy('id');

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        $enrollments = $query->paginate(30)->withQueryString();

        return view('departmental.program-chair.students.elective-override', compact(
            'enrollments', 'electiveSubjects', 'currentTerm'
        ));
    }

    public function changeElective(Request $request, StudentSubjectEnrollment $enrollment) {
        $request->validate([
            'new_subject_id' => 'required|exists:subjects,id',
            'reason'         => 'required|string|max:300',
        ]);

        $enrollment->update(['subject_id' => $request->new_subject_id]);
        // Log the reason in session flash for now
        return back()->with('success', 'Elective changed. Reason: ' . $request->reason);
    }

    // ── Promotion processing ──────────────────────────────────────────────────
    public function promotions(Request $request) {
        $programIds = $this->programIds();

        $promotions = TermPromotion::whereHas('student', fn($q) => $q->whereIn('program_id', $programIds))
            ->with(['student.user', 'student.batch', 'currentTerm', 'promotedToTerm'])
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $terms = Term::orderBy('start_date')->get();

        return view('departmental.program-chair.students.promotions', compact('promotions', 'terms'));
    }
}
