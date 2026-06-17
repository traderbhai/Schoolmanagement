<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{Student, Attendance, ExamResult, StudentSubjectEnrollment, TermPromotion};
use App\Services\GradeService;
use Illuminate\Support\Facades\Auth;

class AcademicSummaryController extends Controller {
    public function index() {
        $student = Student::where('user_id', Auth::id())->firstOrFail();
        $student->load(['user', 'program', 'batch', 'department', 'mentor.user', 'currentTerm']);

        $gradeService = app(GradeService::class);

        // Credits earned vs required
        $enrolledSubjects = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->with('subject')
            ->get();
        $totalCreditsEnrolled = $enrolledSubjects->sum(fn($e) => $e->subject->credits ?? 0);

        // Current term attendance %
        $totalSessions = Attendance::where('student_id', $student->id)->count();
        $presentSessions = Attendance::where('student_id', $student->id)
            ->where('status', 'present')->count();
        $overallAttendance = $totalSessions > 0 ? round($presentSessions / $totalSessions * 100, 1) : null;

        // CGPA
        $cgpa = null;
        try { $cgpa = $gradeService->calculateCGPA($student->id); } catch (\Throwable) {}

        // All exam results grouped by term
        $results = ExamResult::where('student_id', $student->id)
            ->whereHas('exam', fn($query) => $query->whereNotNull('published_at'))
            ->with(['exam.subject'])
            ->orderByDesc('id')
            ->get();

        $passedCount  = $results->filter(fn($r) => ($r->marks_obtained / max($r->exam->total_marks ?? 1, 1)) * 100 >= 35)->count();
        $failedCount  = $results->count() - $passedCount;

        // Promotion history
        $promotions = TermPromotion::where('student_id', $student->id)
            ->with(['currentTerm', 'promotedToTerm'])
            ->orderBy('id')
            ->get();

        return view('student.summary.index', compact(
            'student', 'cgpa', 'overallAttendance',
            'totalCreditsEnrolled', 'passedCount', 'failedCount',
            'promotions', 'enrolledSubjects'
        ));
    }
}
