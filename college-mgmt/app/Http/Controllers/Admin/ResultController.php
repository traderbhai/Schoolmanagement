<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Student, Semester, Course};
use App\Services\GradeService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private GradeService $gradeService) {}

    public function index(Request $request)
    {
        $students  = Student::with('user')->where('status', 'active')->get();
        $semesters = Semester::with('academicYear')->latest()->get();
        $report    = null;
        $student   = null;
        $semester  = null;
        $cgpa      = null;

        if ($request->student_id && $request->semester_id) {
            $student  = Student::with(['user', 'course', 'department'])->findOrFail($request->student_id);
            $semester = Semester::with('academicYear')->findOrFail($request->semester_id);
            $report   = $this->gradeService->calculateStudentSemesterReport($request->student_id, $request->semester_id);
            $cgpa     = $this->gradeService->calculateCGPA($request->student_id);
        }

        return view('admin.results.index', compact('students', 'semesters', 'report', 'student', 'semester', 'cgpa'));
    }
}
