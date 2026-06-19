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
                ->join('timetable_entries', 'attendances.timetable_entry_id', '=', 'timetable_entries.id')
                ->tap(fn ($query) => $this->publishedTimetableJoinScope($query))
                ->selectRaw('timetable_entries.subject_id, COUNT(*) as total, SUM(CASE WHEN attendances.status="present" THEN 1 ELSE 0 END) as present')
                ->groupBy('timetable_entries.subject_id')
                ->get();

            $lowAtt = $attBySubject->filter(fn($r) => $r->total > 0 && ($r->present / $r->total) < 0.75);
            if ($lowAtt->isNotEmpty()) {
                $risks[] = 'attendance';
            }

            // Academic risk: CGPA < 5.0 (approx from results)
            $results = ExamResult::where('student_id', $student->id)
                ->whereHas('exam', fn($q) => $q->whereNotNull('published_at'))
                ->with('exam')
                ->get();
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

    public function mentors(Request $request) {
        $programIds = $this->programIds();

        $query = Student::whereIn('program_id', $programIds)
            ->where('status', 'active')
            ->with(['user', 'batch', 'mentor']);

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

        $mentorUserId = $request->filled('mentor_id')
            ? Teacher::whereKey($request->mentor_id)->value('user_id')
            : null;

        Student::where('id', $request->student_id)->update(['mentor_id' => $mentorUserId]);
        return back()->with('success', 'Mentor updated.');
    }

    public function bulkAssignMentor(Request $request) {
        $request->validate([
            'batch_id'  => 'required|exists:batches,id',
            'mentor_id' => 'required|exists:teachers,id',
        ]);

        $mentorUserId = Teacher::whereKey($request->mentor_id)->value('user_id');

        Student::where('batch_id', $request->batch_id)
            ->where('status', 'active')
            ->update(['mentor_id' => $mentorUserId]);

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

        abort_unless(in_array((int) $leave->student?->program_id, $this->programIds(), true), 403);

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Only pending leave applications can be approved.');
        }

        $leave->update([
            'status'         => 'approved',
            'reviewed_by'    => Auth::id(),
            'admin_remarks'  => $request->remarks,
            'reviewed_at'    => now(),
        ]);
        return back()->with('success', 'Leave approved.');
    }

    public function rejectLeave(Request $request, LeaveApplication $leave) {
        $request->validate(['remarks' => 'required|string|max:300']);

        abort_unless(in_array((int) $leave->student?->program_id, $this->programIds(), true), 403);

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Only pending leave applications can be rejected.');
        }

        $leave->update([
            'status'         => 'rejected',
            'reviewed_by'    => Auth::id(),
            'admin_remarks'  => $request->remarks,
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
        abort_unless(in_array((int) $condonation->student?->program_id, $this->programIds(), true), 403);

        if ($condonation->status !== 'pending') {
            return back()->with('error', 'Only pending condonation requests can be approved.');
        }

        $requested = max(1, (int) ($condonation->sessions_requested ?: $condonation->sessions_condoned ?: 1));

        $request->validate([
            'sessions_condoned' => 'required|integer|min:1|max:' . $requested,
            'remarks'           => 'nullable|string|max:300',
        ]);

        $condonation->update([
            'status'            => 'approved',
            'sessions_condoned' => $request->sessions_condoned,
            'approved_by'       => Auth::id(),
            'remarks'           => $request->remarks,
            'reviewed_at'       => now(),
        ]);
        return back()->with('success', 'Condonation approved.');
    }

    public function rejectCondonation(Request $request, AttendanceCondonation $condonation) {
        $request->validate(['remarks' => 'required|string|max:300']);

        abort_unless(in_array((int) $condonation->student?->program_id, $this->programIds(), true), 403);

        if ($condonation->status !== 'pending') {
            return back()->with('error', 'Only pending condonation requests can be rejected.');
        }

        $condonation->update([
            'status'      => 'rejected',
            'approved_by' => Auth::id(),
            'remarks'     => $request->remarks,
            'reviewed_at' => now(),
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
        $programIds = $this->programIds();
        abort_unless(in_array((int) $grievance->program_id, array_map('intval', $programIds), true), 403);

        $data = $request->validate([
            'status'           => 'required|in:open,under_review,escalated,resolved',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        if (in_array($grievance->status, ['resolved', 'closed'], true)) {
            return back()->with('error', 'Resolved or closed grievance history cannot be changed from Program Chair operations.');
        }

        if ($data['status'] === 'escalated' && ! in_array($grievance->status, ['open', 'under_review'], true)) {
            return back()->with('error', 'Only open or under review grievances can be escalated.');
        }

        $resolutionNotes = trim((string) ($data['resolution_notes'] ?? ''));
        if ($data['status'] === 'resolved' && $resolutionNotes === '') {
            return back()->withErrors(['resolution_notes' => 'Resolution notes are required before resolving a grievance.']);
        }

        $grievance->update([
            'status'           => $data['status'],
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'resolved_by'      => $data['status'] === 'resolved' ? Auth::id() : null,
            'resolved_at'      => $data['status'] === 'resolved' ? now() : null,
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
        $data = $request->validate([
            'new_subject_id' => 'required|exists:subjects,id',
            'reason'         => 'required|string|max:300',
        ]);

        $enrollment->load('student');
        $programIds = array_map('intval', $this->programIds());
        abort_unless(in_array((int) $enrollment->student?->program_id, $programIds, true), 403);

        if ($enrollment->enrollment_type !== 'elective' || $enrollment->status !== 'active') {
            return back()->with('error', 'Only active elective enrollments can be changed from elective override.');
        }

        if ((int) $enrollment->subject_id === (int) $data['new_subject_id']) {
            return back()->withErrors(['new_subject_id' => 'Select a different elective subject.']);
        }

        $newProgramSubject = ProgramSubject::where('program_id', $enrollment->student->program_id)
            ->where('subject_id', $data['new_subject_id'])
            ->where('type', 'elective')
            ->where('is_active', true)
            ->when($enrollment->term_id, fn($query) => $query->where('term_id', $enrollment->term_id))
            ->first();

        if (! $newProgramSubject) {
            return back()->withErrors(['new_subject_id' => 'Replacement subject must be an active elective for the same program and term.']);
        }

        $duplicate = StudentSubjectEnrollment::where('student_id', $enrollment->student_id)
            ->where('subject_id', $data['new_subject_id'])
            ->where(function ($query) use ($enrollment) {
                $enrollment->term_id
                    ? $query->where('term_id', $enrollment->term_id)
                    : $query->whereNull('term_id');
            })
            ->where('id', '!=', $enrollment->id)
            ->where('status', 'active')
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['new_subject_id' => 'Student already has an active enrollment for this subject in the same term.']);
        }

        $enrollment->update([
            'previous_subject_id' => $enrollment->subject_id,
            'subject_id'          => $data['new_subject_id'],
            'override_reason'     => $data['reason'],
            'overridden_by'       => Auth::id(),
            'overridden_at'       => now(),
        ]);

        return back()->with('success', 'Elective changed with override reason recorded.');
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
