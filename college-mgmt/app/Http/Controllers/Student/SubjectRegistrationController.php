<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\Student;
use Illuminate\Http\Request;

class SubjectRegistrationController extends Controller
{
    private function getStudent(): ?Student
    {
        return Student::where('user_id', auth()->id())->first();
    }

    public function index()
    {
        $student = $this->getStudent();
        if (!$student) {
            return view('student.subject-registration', [
                'student'            => null,
                'availableSubjects'  => collect(),
                'enrolledSubjects'   => collect(),
                'currentTerm'        => null,
            ]);
        }

        $currentTerm = $student->currentTerm;

        // Subjects already enrolled in for this term
        $enrolledSubjectIds = Enrollment::where('student_id', $student->id)
            ->where('term_id', $currentTerm?->id)
            ->pluck('subject_id');

        $enrolledSubjects = Enrollment::where('student_id', $student->id)
            ->where('term_id', $currentTerm?->id)
            ->with('subject')
            ->get();

        // Available subjects not yet enrolled
        $availableSubjects = Subject::whereNotIn('id', $enrolledSubjectIds)
            ->where('semester_id', $currentTerm?->id)  // term maps to semester context
            ->orderBy('name')
            ->get();

        // Fallback: if no subjects found via term mapping, show all subjects not enrolled
        if ($availableSubjects->isEmpty() && $currentTerm) {
            $availableSubjects = Subject::whereNotIn('id', $enrolledSubjectIds)
                ->orderBy('name')
                ->limit(30)
                ->get();
        }

        return view('student.subject-registration', compact(
            'student', 'currentTerm', 'enrolledSubjects', 'availableSubjects'
        ));
    }

    public function store(Request $request)
    {
        $student = $this->getStudent();
        if (!$student) {
            return back()->with('error', 'Student profile not found.');
        }

        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $currentTerm = $student->currentTerm;
        if (!$currentTerm) {
            return back()->with('error', 'No current term assigned. Please contact the academic office.');
        }

        // Prevent duplicate enrollment
        $exists = Enrollment::where('student_id', $student->id)
            ->where('term_id', $currentTerm->id)
            ->where('subject_id', $request->subject_id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'You are already enrolled in this subject.');
        }

        Enrollment::create([
            'student_id' => $student->id,
            'term_id'    => $currentTerm->id,
            'subject_id' => $request->subject_id,
            'status'     => 'active',
        ]);

        $subject = Subject::find($request->subject_id);
        return back()->with('success', 'Enrolled in ' . ($subject->name ?? 'subject') . ' successfully.');
    }

    public function destroy(Enrollment $enrollment)
    {
        $student = $this->getStudent();
        if (!$student || $enrollment->student_id !== $student->id) {
            return back()->with('error', 'Unauthorized action.');
        }

        $enrollment->delete();
        return back()->with('success', 'Subject dropped successfully.');
    }
}
