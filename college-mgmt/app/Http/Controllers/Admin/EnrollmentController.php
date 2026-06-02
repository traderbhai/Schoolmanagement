<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Enrollment, Student, Semester, Subject, Course};
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $enrollments = Enrollment::with(['student.user', 'semester', 'subject'])
            ->when($request->semester_id, fn($q,$v) => $q->where('semester_id', $v))
            ->when($request->course_id, fn($q,$v) => $q->whereHas('student', fn($s) => $s->where('course_id', $v)))
            ->latest()->paginate(25)->withQueryString();

        $semesters = Semester::with('academicYear')->latest()->get();
        $courses = Course::where('is_active', true)->get();
        return view('admin.enrollments.index', compact('enrollments', 'semesters', 'courses'));
    }

    public function create()
    {
        $students = Student::with(['user', 'course'])->where('status', 'active')->get();
        $semesters = Semester::with('academicYear')->latest()->get();
        $subjects = Subject::where('is_active', true)->get();
        return view('admin.enrollments.create', compact('students', 'semesters', 'subjects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id'  => 'required|exists:students,id',
            'semester_id' => 'required|exists:semesters,id',
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $created = 0;
        foreach ($request->subject_ids as $subjectId) {
            Enrollment::firstOrCreate(
                ['student_id' => $request->student_id, 'semester_id' => $request->semester_id, 'subject_id' => $subjectId],
                ['status' => 'active']
            );
            $created++;
        }

        return redirect()->route('admin.enrollments.index')->with('success', "$created subject(s) enrolled successfully.");
    }

    public function bulkEnroll(Request $request)
    {
        $request->validate([
            'course_id'   => 'required|exists:courses,id',
            'semester_id' => 'required|exists:semesters,id',
            'subject_ids' => 'required|array|min:1',
        ]);

        $students = Student::where('course_id', $request->course_id)->where('status', 'active')->get();
        $count = 0;
        foreach ($students as $student) {
            foreach ($request->subject_ids as $subjectId) {
                Enrollment::firstOrCreate(
                    ['student_id' => $student->id, 'semester_id' => $request->semester_id, 'subject_id' => $subjectId],
                    ['status' => 'active']
                );
                $count++;
            }
        }

        return redirect()->route('admin.enrollments.index')->with('success', "Bulk enrollment: $count records created for {$students->count()} students.");
    }

    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();
        return back()->with('success', 'Enrollment removed.');
    }
}
