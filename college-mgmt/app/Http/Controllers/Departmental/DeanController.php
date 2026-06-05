<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Program, Student, Teacher, Exam, ExamResult, Attendance, Batch, Term};
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

        // Attendance % for current period
        $totalAtt  = Attendance::count();
        $presentAtt = Attendance::where('status', 'present')->count();
        $attendancePct = $totalAtt > 0 ? round(($presentAtt / $totalAtt) * 100, 1) : 0;

        $programs = Program::where('is_active', true)
            ->withCount(['students' => fn($q) => $q->where('status', 'active'), 'batches'])
            ->get();

        $recentResults = ExamResult::with(['exam.program', 'student.user'])
            ->latest()
            ->take(10)
            ->get();

        return view('departmental.dean.dashboard', compact(
            'totalPrograms', 'totalStudents', 'totalFaculty',
            'totalExams', 'attendancePct', 'programs', 'recentResults'
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
            ->having('exam_results_count', '>', 0)
            ->get()
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
}
