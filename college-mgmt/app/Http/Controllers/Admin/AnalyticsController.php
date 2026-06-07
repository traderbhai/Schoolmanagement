<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Student, Program, Exam, ExamResult, Attendance, FeePayment, FeeStructure, Placement, Teacher, Batch};
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index()
    {
        $currentYear = now()->year;

        // ── Program-wise cross-domain summary ─────────────────────────────────
        $programs = Program::where('is_active', true)
            ->withCount(['students' => fn($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get()
            ->map(function ($prog) use ($currentYear) {
                $studentIds = Student::where('program_id', $prog->id)
                    ->where('status', 'active')
                    ->pluck('id');

                // Pass rate from ExamResult
                $results = ExamResult::whereHas('exam', fn($q) => $q->where('program_id', $prog->id))->get();
                $total = $results->count();
                $passed = $results->filter(fn($r) => !$r->is_absent && $r->exam
                    && $r->marks_obtained >= $r->exam->passing_marks)->count();
                $prog->pass_rate = $total > 0 ? round(($passed / $total) * 100, 1) : null;

                // Attendance
                $attTotal   = Attendance::whereIn('student_id', $studentIds)->count();
                $attPresent = Attendance::whereIn('student_id', $studentIds)->where('status', 'present')->count();
                $prog->att_pct = $attTotal > 0 ? round(($attPresent / $attTotal) * 100, 1) : null;

                // Placement rate this year
                $placed = Placement::whereIn('student_id', $studentIds)
                    ->where('application_status', 'selected')
                    ->whereYear('created_at', $currentYear)->count();
                $prog->placement_rate = $prog->students_count > 0
                    ? round(($placed / $prog->students_count) * 100, 1) : null;

                return $prog;
            });

        // ── Exam grade distribution ────────────────────────────────────────────
        // Compute grade letters from marks_obtained vs exam total_marks
        $examResults = ExamResult::with('exam')
            ->whereYear('created_at', $currentYear)
            ->get();

        $gradeDistribution = ['O' => 0, 'A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0, 'Absent' => 0];
        foreach ($examResults as $r) {
            if ($r->is_absent) { $gradeDistribution['Absent']++; continue; }
            if (!$r->exam) continue;
            $max = $r->exam->total_marks ?: 100;
            $pct = ($r->marks_obtained / $max) * 100;
            $grade = match(true) {
                $pct >= 90  => 'O',
                $pct >= 80  => 'A+',
                $pct >= 70  => 'A',
                $pct >= 60  => 'B+',
                $pct >= 50  => 'B',
                $pct >= 40  => 'C',
                $pct >= 35  => 'D',
                default     => 'F',
            };
            $gradeDistribution[$grade]++;
        }

        // ── Monthly fee collection (last 6 months) ────────────────────────────
        $monthlyFees = [];
        for ($i = 5; $i >= 0; $i--) {
            $month  = now()->subMonths($i);
            $amount = FeePayment::where('status', 'paid')
                ->whereYear('payment_date', $month->year)
                ->whereMonth('payment_date', $month->month)
                ->sum('amount_paid');
            $monthlyFees[] = ['label' => $month->format('M Y'), 'amount' => (float) $amount];
        }

        // ── Student enrollment trend (last 6 months by admission_date) ────────
        $enrollmentTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = Student::whereYear('admission_date', $month->year)
                ->whereMonth('admission_date', $month->month)
                ->count();
            $enrollmentTrend[] = ['label' => $month->format('M Y'), 'count' => $count];
        }

        // ── At-risk students (attendance < 65%) ───────────────────────────────
        $atRisk = Student::where('status', 'active')
            ->with(['user', 'program'])
            ->get()
            ->map(function ($s) {
                $total   = Attendance::where('student_id', $s->id)->count();
                $present = Attendance::where('student_id', $s->id)->where('status', 'present')->count();
                $s->att_pct = $total > 0 ? round(($present / $total) * 100, 1) : null;
                return $s;
            })
            ->filter(fn($s) => $s->att_pct !== null && $s->att_pct < 65)
            ->sortBy('att_pct')
            ->take(10);

        // ── Summary KPIs ──────────────────────────────────────────────────────
        $totalStudents   = Student::where('status', 'active')->count();
        $totalTeachers   = Teacher::where('status', 'active')->count();
        $overallPassRate = $examResults->count() > 0
            ? round(($examResults->filter(fn($r) => !$r->is_absent && $r->exam
                && $r->marks_obtained >= $r->exam->passing_marks)->count() / $examResults->count()) * 100, 1)
            : null;
        $overallAttPct = Attendance::count() > 0
            ? round((Attendance::where('status', 'present')->count() / Attendance::count()) * 100, 1)
            : null;

        return view('admin.analytics', compact(
            'programs', 'gradeDistribution', 'monthlyFees', 'enrollmentTrend',
            'atRisk', 'totalStudents', 'totalTeachers', 'overallPassRate', 'overallAttPct',
            'currentYear'
        ));
    }
}
