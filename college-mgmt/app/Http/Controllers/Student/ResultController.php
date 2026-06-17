<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{Semester, ExamResult};
use App\Services\GradeService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private GradeService $gradeService) {}

    public function index(Request $request)
    {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('student.dashboard');

        $semesters = $this->gradeService->semestersForStudent($student->id)
            ->load('academicYear')
            ->sortByDesc('number')
            ->values();

        $currentSemester = Semester::current();
        $semesterId = $request->semester_id ?? optional($currentSemester)->id ?? optional($semesters->first())->id;

        $report = null;
        $cgpa   = null;
        if ($semesterId) {
            $report = $this->gradeService->calculateStudentSemesterReport($student->id, $semesterId);
            $cgpa   = $this->gradeService->calculateCGPA($student->id);

            // Eager-load assessment components for every result
            foreach ($report['subjects'] as &$row) {
                foreach ($row['results'] as $result) {
                    $result->load('assessmentComponents');
                }
            }
            unset($row);
        }

        // Backlogs: failed subjects across ALL semesters
        $backlogs = ExamResult::with(['exam.subject', 'exam.semester'])
            ->where('student_id', $student->id)
            ->where('is_absent', false)
            ->whereHas('exam', fn($q) => $q->whereNotNull('published_at'))
            ->get()
            ->filter(function ($r) {
                if (!$r->exam || $r->exam->total_marks <= 0) return false;
                $pct = ($r->marks_obtained / $r->exam->total_marks) * 100;
                return $pct < 35;
            });

        return view('student.results', compact('semesters', 'semesterId', 'report', 'cgpa', 'student', 'backlogs'));
    }
}
