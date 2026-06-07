<?php
namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Program, Term, Student, Subject, Exam, ExamResult, Attendance,
                Batch, FeeDemand, RoleProgramAssignment, TimetableEntry};
use App\Services\GradeService;
use Illuminate\Http\Request;
use PDF;

class PmcReportsController extends Controller
{
    private function programIds(): array
    {
        $ids = RoleProgramAssignment::where('user_id', auth()->id())
            ->where('is_active', true)->pluck('program_id')->toArray();
        return $ids ?: Program::where('is_active', true)->pluck('id')->toArray();
    }

    // ── Subject performance report ────────────────────────────────────────────
    public function subjectPerformance(Request $request)
    {
        $programIds  = $this->programIds();
        $currentTerm = Term::latest('start_date')->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : $currentTerm;

        $subjects = Subject::whereIn('program_id', $programIds)
            ->when($selectedTerm, fn($q) => $q->where('term_id', $selectedTerm->id))
            ->with('program')
            ->get()
            ->map(function ($subject) {
                $exams = Exam::where('subject_id', $subject->id)->get();
                $results = ExamResult::whereIn('exam_id', $exams->pluck('id'))->get();

                if ($results->isEmpty()) {
                    $subject->stats = null;
                    return $subject;
                }

                $pcts = $results->map(fn($r) => $r->exam
                    ? ($r->marks_obtained / max($r->exam->total_marks ?? 100, 1)) * 100
                    : null)->filter();

                $subject->stats = (object)[
                    'count'      => $results->count(),
                    'avg'        => round($pcts->avg(), 1),
                    'max'        => round($pcts->max(), 1),
                    'min'        => round($pcts->min(), 1),
                    'pass_rate'  => round($results->filter(fn($r) => $r->marks_obtained >= ($r->exam->passing_marks ?? 40))->count() / $results->count() * 100, 1),
                    'dist'       => [
                        'O'  => $pcts->filter(fn($p) => $p >= 90)->count(),
                        'A+' => $pcts->filter(fn($p) => $p >= 80 && $p < 90)->count(),
                        'A'  => $pcts->filter(fn($p) => $p >= 70 && $p < 80)->count(),
                        'B+' => $pcts->filter(fn($p) => $p >= 60 && $p < 70)->count(),
                        'B'  => $pcts->filter(fn($p) => $p >= 50 && $p < 60)->count(),
                        'F'  => $pcts->filter(fn($p) => $p < 40)->count(),
                    ],
                ];
                return $subject;
            });

        $terms = Term::orderByDesc('start_date')->take(8)->get();

        return view('departmental.program-chair.reports.subject-performance', compact(
            'subjects', 'terms', 'selectedTerm'
        ));
    }

    // ── Attendance defaulters ─────────────────────────────────────────────────
    public function attendanceDefaulters(Request $request)
    {
        $programIds  = $this->programIds();
        $threshold   = (int) $request->get('threshold', 75);

        $students = Student::whereIn('program_id', $programIds)
            ->where('status', 'active')
            ->with(['user', 'program', 'batch'])
            ->get();

        $defaulters = $students->map(function ($student) use ($threshold) {
            $attBySubject = Attendance::where('student_id', $student->id)
                ->selectRaw('subject_id, COUNT(*) as total, SUM(CASE WHEN status="present" THEN 1 ELSE 0 END) as present')
                ->groupBy('subject_id')
                ->get();

            $low = $attBySubject->filter(fn($r) => $r->total > 0 && ($r->present / $r->total) * 100 < $threshold);
            if ($low->isEmpty()) return null;

            $overall_pct = $attBySubject->sum('total') > 0
                ? round($attBySubject->sum('present') / $attBySubject->sum('total') * 100, 1)
                : null;

            $student->low_subjects  = $low->count();
            $student->overall_pct   = $overall_pct;
            $student->parent_phone  = $student->guardian_phone ?? null;
            return $student;
        })->filter()->sortBy('overall_pct');

        $programs = Program::whereIn('id', $programIds)->get();
        $batches  = Batch::whereIn('program_id', $programIds)->orderBy('name')->get();

        return view('departmental.program-chair.reports.attendance-defaulters', compact(
            'defaulters', 'programs', 'batches', 'threshold'
        ));
    }

    // ── Term-end summary PDF ──────────────────────────────────────────────────
    public function termSummary(Request $request)
    {
        $programIds  = $this->programIds();
        $currentTerm = Term::latest('start_date')->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : $currentTerm;

        $programs = Program::whereIn('id', $programIds)->with('batches')->get();

        $stats = $programs->map(function ($program) use ($selectedTerm) {
            $students = Student::where('program_id', $program->id)->where('status', 'active')->get();
            $exams    = Exam::where('program_id', $program->id)
                ->when($selectedTerm, fn($q) => $q->where('term_id', $selectedTerm->id))
                ->get();
            $results  = ExamResult::whereIn('exam_id', $exams->pluck('id'))->get();

            $pcts = $results->map(fn($r) => $r->exam
                ? ($r->marks_obtained / max($r->exam->total_marks ?? 100, 1)) * 100
                : null)->filter();

            $attTotal   = Attendance::whereHas('student', fn($q) => $q->where('program_id', $program->id))->count();
            $attPresent = Attendance::whereHas('student', fn($q) => $q->where('program_id', $program->id))->where('status', 'present')->count();

            return (object)[
                'program'      => $program,
                'enrollment'   => $students->count(),
                'exam_count'   => $exams->count(),
                'avg_marks'    => $pcts->isNotEmpty() ? round($pcts->avg(), 1) : null,
                'pass_rate'    => $results->isNotEmpty()
                    ? round($results->filter(fn($r) => $r->marks_obtained >= ($r->exam->passing_marks ?? 40))->count() / $results->count() * 100, 1)
                    : null,
                'att_pct'      => $attTotal > 0 ? round($attPresent / $attTotal * 100, 1) : null,
                'top_students' => $students->sortByDesc(fn($s) =>
                    ExamResult::where('student_id', $s->id)->avg('marks_obtained')
                )->take(5),
            ];
        });

        if ($request->get('pdf')) {
            $pdf = PDF::loadView('departmental.program-chair.reports.term-summary-pdf', compact(
                'stats', 'selectedTerm', 'programs'
            ));
            return $pdf->download('term-summary-' . ($selectedTerm->name ?? 'report') . '.pdf');
        }

        $terms = Term::orderByDesc('start_date')->take(8)->get();

        return view('departmental.program-chair.reports.term-summary', compact(
            'stats', 'terms', 'selectedTerm'
        ));
    }
}
