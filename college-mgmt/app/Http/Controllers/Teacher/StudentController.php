<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\{Student, TimetableEntry, Semester};

class StudentController extends Controller
{
    public function index()
    {
        $teacher = auth()->user()->teacher;
        $currentSemester = Semester::current();

        // Students enrolled in subjects this teacher teaches
        $subjectIds = TimetableEntry::where('teacher_id', $teacher->id)
            ->when($currentSemester, fn($q) => $q->where('semester_id', $currentSemester->id))
            ->where('is_active', true)
            ->pluck('subject_id')->unique();

        $students = Student::whereHas('enrollments', fn($q) =>
            $q->whereIn('subject_id', $subjectIds)
              ->when($currentSemester, fn($q2) => $q2->where('semester_id', $currentSemester->id))
        )->with(['user', 'course', 'department'])
         ->orderBy('roll_number')
         ->get();

        return view('teacher.students', compact('students', 'currentSemester'));
    }
}
