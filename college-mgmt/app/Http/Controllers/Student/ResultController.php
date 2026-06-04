<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Services\GradeService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private GradeService $gradeService) {}

    public function index(Request $request)
    {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('student.dashboard');

        $semesters = Semester::whereHas('enrollments', fn($q) => $q->where('student_id', $student->id))
            ->with('academicYear')->orderByDesc('number')->get();

        $currentSemester = Semester::current();
        $semesterId = $request->semester_id ?? optional($currentSemester)->id ?? optional($semesters->first())->id;

        $report = null;
        $cgpa   = null;
        if ($semesterId) {
            $report = $this->gradeService->calculateStudentSemesterReport($student->id, $semesterId);
            $cgpa   = $this->gradeService->calculateCGPA($student->id);
        }

        return view('student.results', compact('semesters', 'semesterId', 'report', 'cgpa', 'student'));
    }
}
