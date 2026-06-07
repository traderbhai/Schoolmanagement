<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{Semester, Notice, TimetableSlot, Attendance, FeeStructure, FeePayment, FeeDemand, ExamResult, Exam};
use App\Services\TimetableService;

class DashboardController extends Controller
{
    public function __construct(private TimetableService $service) {}

    public function index() {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('login');

        $currentSemester = Semester::current();
        $notices = Notice::active()->where(fn($q) => $q->where('audience','all')->orWhere('audience','students'))->latest()->take(5)->get();
        $slots = TimetableSlot::where('is_active',true)->orderBy('sort_order')->get();
        $grid = $currentSemester ? $this->service->buildWeeklyGrid($currentSemester->id, $student->course_id) : [];

        $todayDay = now()->dayOfWeekIso; // 1=Mon...6=Sat
        $todayClasses = [];
        if (isset($grid[$todayDay])) {
            $todayClasses = array_filter($grid[$todayDay]);
        }

        // Attendance overall
        $totalAttendance   = Attendance::where('student_id', $student->id)->count();
        $presentAttendance = Attendance::where('student_id', $student->id)->whereIn('status', ['present','late'])->count();
        $attendanceOverall = $totalAttendance > 0 ? round(($presentAttendance / $totalAttendance) * 100) : null;

        // SGPA — compute from marks_obtained / total_marks * 10 for current semester
        $sgpa = null;
        if ($currentSemester) {
            $results = ExamResult::with('exam')
                ->whereHas('exam', fn($q) => $q->where('semester_id', $currentSemester->id))
                ->where('student_id', $student->id)
                ->where('is_absent', false)
                ->get();
            if ($results->count() > 0) {
                $totalGp = $results->sum(fn($r) => $r->exam->total_marks > 0
                    ? ($r->marks_obtained / $r->exam->total_marks) * 10 : 0);
                $sgpa = round($totalGp / $results->count(), 2);
            }
        }

        // CGPA — avg across all semesters
        $cgpa = null;
        try {
            $allResults = ExamResult::with('exam')
                ->where('student_id', $student->id)
                ->where('is_absent', false)
                ->get()
                ->filter(fn($r) => $r->exam && $r->exam->total_marks > 0);
            if ($allResults->count() > 0) {
                $totalGp = $allResults->sum(fn($r) => ($r->marks_obtained / $r->exam->total_marks) * 10);
                $cgpa = round($totalGp / $allResults->count(), 2);
            }
        } catch (\Exception $e) { $cgpa = null; }

        // Fee balance due
        $feeDue = FeeStructure::where('course_id', $student->course_id)->sum('amount');
        $feePaid = FeePayment::where('student_id', $student->id)->where('status', 'paid')->sum('amount_paid');
        $balanceDue = max(0, $feeDue - $feePaid);

        // Fee outstanding from FeeDemand
        $feeOutstanding = 0;
        try {
            $feeOutstanding = FeeDemand::where('student_id', $student->id)
                ->where('status', '!=', 'paid')
                ->sum('amount');
            $balanceDue = max($balanceDue, $feeOutstanding);
        } catch (\Exception $e) {}

        // Upcoming exams
        $upcomingExams = [];
        try {
            if ($student->program_id) {
                $upcomingExams = Exam::where('program_id', $student->program_id)
                    ->where('exam_date', '>', now())
                    ->with('subject')
                    ->orderBy('exam_date')
                    ->take(5)
                    ->get();
            }
        } catch (\Exception $e) { $upcomingExams = collect(); }

        return view('student.dashboard', compact(
            'student', 'currentSemester', 'notices', 'slots', 'grid', 'todayClasses',
            'attendanceOverall', 'sgpa', 'cgpa', 'balanceDue', 'upcomingExams'
        ));
    }
}
