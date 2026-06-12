<?php
namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Program, Term, Teacher, TimetableEntry, TimetableSlot, Subject,
                SubjectFacultyAssignment, Attendance, CourseFeedback, Exam, ExamResult,
                RoleProgramAssignment, AssessmentComponent, ProgramSubject};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmcFacultyController extends Controller
{
    private function programIds(): array
    {
        $ids = RoleProgramAssignment::where('user_id', Auth::id())
            ->where('is_active', true)->pluck('program_id')->toArray();
        return $ids ?: Program::where('is_active', true)->pluck('id')->toArray();
    }

    // ── Faculty workload ──────────────────────────────────────────────────────
    public function workload(Request $request)
    {
        $programIds  = $this->programIds();
        $currentTerm = Term::latest('start_date')->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id)
            : $currentTerm;

        $slots = TimetableSlot::where('is_active', true)->get()->keyBy('id');

        // All teachers assigned to entries in these programs this term
        $entries = TimetableEntry::whereIn('program_id', $programIds)
            ->where('term_id', $selectedTerm?->id)
            ->where('is_active', true)
            ->with(['teacher.user', 'slot', 'subject', 'batch'])
            ->get();

        // Compute hours/week per teacher
        $workload = $entries->groupBy('teacher_id')->map(function ($teacherEntries) use ($slots) {
            $teacher = $teacherEntries->first()?->teacher;
            if (!$teacher) return null;
            $hours = $teacherEntries->sum(function ($e) use ($slots) {
                $slot = $slots[$e->timetable_slot_id] ?? null;
                if (!$slot) return 0;
                $start = strtotime($slot->start_time);
                $end   = strtotime($slot->end_time);
                return ($end - $start) / 3600;
            });
            return (object)[
                'teacher'  => $teacher,
                'sessions' => $teacherEntries->count(),
                'hours'    => round($hours, 1),
                'subjects' => $teacherEntries->pluck('subject.name')->unique()->filter()->values(),
                'batches'  => $teacherEntries->pluck('batch.name')->unique()->filter()->values(),
                'overload' => $hours > 18,
            ];
        })->filter()->sortByDesc('hours')->values();

        $terms = Term::orderByDesc('start_date')->take(8)->get();

        return view('departmental.program-chair.faculty.workload', compact(
            'workload', 'terms', 'selectedTerm'
        ));
    }

    // ── Marks submission tracker ──────────────────────────────────────────────
    public function marksTracker(Request $request)
    {
        $programIds  = $this->programIds();
        $currentTerm = Term::latest('start_date')->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : $currentTerm;

        // All assessment components for subjects in these programs this term
        $components = AssessmentComponent::whereHas('subject', fn($q) =>
            $q->whereIn('program_id', $programIds))
            ->where('term_id', $selectedTerm?->id)
            ->with(['subject.program', 'exam'])
            ->get()
            ->map(function ($comp) {
                // Check if exam results exist for this component's exam
                $comp->submitted = $comp->exam
                    ? ExamResult::where('exam_id', $comp->exam->id)->exists()
                    : false;
                $comp->result_count = $comp->exam
                    ? ExamResult::where('exam_id', $comp->exam->id)->count()
                    : 0;
                return $comp;
            });

        // Group by subject
        $bySubject = $components->groupBy('subject_id');

        $terms = Term::orderByDesc('start_date')->take(8)->get();

        return view('departmental.program-chair.faculty.marks-tracker', compact(
            'bySubject', 'terms', 'selectedTerm'
        ));
    }

    // ── Course delivery tracking ──────────────────────────────────────────────
    public function courseDelivery(Request $request)
    {
        $programIds  = $this->programIds();
        $currentTerm = Term::latest('start_date')->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : $currentTerm;

        // Faculty assignments with their subject
        $assignments = SubjectFacultyAssignment::whereIn('program_id', $programIds)
            ->where('term_id', $selectedTerm?->id)
            ->with(['subject', 'teacher.user', 'batch'])
            ->get()
            ->map(function ($a) {
                // Count attendance records as a proxy for sessions conducted
                $total     = $a->subject ? ($a->subject->hours_per_week ?? 3) * 16 : 48; // 16-week term estimate
                $conducted = Attendance::where('subject_id', $a->subject_id)
                    ->when($a->batch_id, fn($q) => $q->whereHas('student', fn($s) => $s->where('batch_id', $a->batch_id)))
                    ->select('date')->distinct()->count();
                $a->planned   = $total;
                $a->conducted = $conducted;
                $a->pct       = $total > 0 ? min(100, round(($conducted / $total) * 100)) : 0;
                return $a;
            });

        $terms = Term::orderByDesc('start_date')->take(8)->get();

        return view('departmental.program-chair.faculty.course-delivery', compact(
            'assignments', 'terms', 'selectedTerm'
        ));
    }

    // ── Faculty feedback summary ──────────────────────────────────────────────
    public function feedbackSummary(Request $request)
    {
        $programIds  = $this->programIds();
        $currentTerm = Term::latest('start_date')->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : $currentTerm;

        // Aggregate feedback per teacher per subject
        $feedbackData = CourseFeedback::whereHas('subject', fn($q) => $q->whereIn('program_id', $programIds))
            ->when($selectedTerm, fn($q) => $q->whereHas('subject', fn($sq) =>
                $sq->where('term_id', $selectedTerm->id)))
            ->with(['subject'])
            ->get()
            ->groupBy('subject_id')
            ->map(function ($rows, $subjectId) {
                $subject  = $rows->first()?->subject;
                if (!$subject) return null;
                $avg_teaching = $rows->whereNotNull('teaching_rating')->avg('teaching_rating');
                $avg_content  = $rows->whereNotNull('content_rating')->avg('content_rating');
                $avg_overall  = $rows->whereNotNull('overall_rating')->avg('overall_rating');
                return (object)[
                    'subject'      => $subject,
                    'count'        => $rows->count(),
                    'avg_teaching' => $avg_teaching ? round($avg_teaching, 1) : null,
                    'avg_content'  => $avg_content  ? round($avg_content, 1) : null,
                    'avg_overall'  => $avg_overall  ? round($avg_overall, 1) : null,
                ];
            })->filter()->values();

        // Map feedback to teacher assignments
        $assignments = SubjectFacultyAssignment::whereIn('program_id', $programIds)
            ->where('term_id', $selectedTerm?->id)
            ->with(['teacher.user', 'subject'])
            ->get();

        $terms = Term::orderByDesc('start_date')->take(8)->get();

        return view('departmental.program-chair.faculty.feedback', compact(
            'feedbackData', 'assignments', 'terms', 'selectedTerm'
        ));
    }
}
