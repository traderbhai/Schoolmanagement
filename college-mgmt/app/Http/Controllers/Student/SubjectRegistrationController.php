<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'canManageSubjects'  => false,
            ]);
        }

        $currentTerm = $student->currentTerm;
        $canManageSubjects = $student->status === 'active';

        $enrolledSubjectIds = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('term_id', $currentTerm?->id)
            ->where('status', 'active')
            ->pluck('subject_id');

        $enrolledSubjects = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('term_id', $currentTerm?->id)
            ->where('status', 'active')
            ->with('subject')
            ->get();

        $availableSubjects = Subject::where('is_active', true)
            ->where('program_id', $student->program_id)
            ->when($currentTerm, fn($q) => $q->where(function ($termQuery) use ($currentTerm) {
                $termQuery->whereNull('term_number')
                    ->orWhere('term_number', $currentTerm->term_number);
            }))
            ->whereNotIn('id', $enrolledSubjectIds)
            ->orderBy('name')
            ->get();

        return view('student.subject-registration', compact(
            'student', 'currentTerm', 'enrolledSubjects', 'availableSubjects', 'canManageSubjects'
        ));
    }

    public function store(Request $request)
    {
        $student = $this->getStudent();
        if (!$student) {
            return back()->with('error', 'Student profile not found.');
        }

        if ($student->status !== 'active') {
            return back()->with('error', 'Subject registration is available only for active students. Contact the academic office for archived records.');
        }

        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $currentTerm = $student->currentTerm;
        if (!$currentTerm) {
            return back()->with('error', 'No current term assigned. Please contact the academic office.');
        }

        $subject = Subject::where('is_active', true)
            ->where('program_id', $student->program_id)
            ->where('id', $request->subject_id)
            ->where(function ($query) use ($currentTerm) {
                $query->whereNull('term_number')
                    ->orWhere('term_number', $currentTerm->term_number);
            })
            ->first();

        if (!$subject) {
            return back()->with('error', 'This subject is not available for your current program and term.');
        }

        $existingEnrollment = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('term_id', $currentTerm->id)
            ->where('subject_id', $request->subject_id)
            ->first();

        if ($existingEnrollment?->status === 'active') {
            return back()->with('error', 'You are already enrolled in this subject.');
        }

        if ($existingEnrollment?->status === 'completed') {
            return back()->with('error', 'Completed subject history cannot be re-registered from self-service.');
        }

        // Check credit limit (max 24 credits per term)
        $maxCredits = 24;
        $currentCredits = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('term_id', $currentTerm->id)
            ->where('status', 'active')
            ->with('subject')
            ->get()
            ->sum(fn($e) => $e->subject?->credits ?? 3);

        $newSubjectCredits = $subject->credits ?? 3;

        if ($currentCredits + $newSubjectCredits > $maxCredits) {
            return back()->with('error', "Credit limit exceeded. Current: {$currentCredits}, Requested: {$newSubjectCredits}, Max: {$maxCredits}.");
        }

        DB::transaction(function () use ($student, $currentTerm, $subject) {
            StudentSubjectEnrollment::updateOrCreate([
                'student_id' => $student->id,
                'term_id' => $currentTerm->id,
                'subject_id' => $subject->id,
            ], [
                'enrollment_type' => 'elective',
                'status' => 'active',
            ]);

            if ($semester = $this->legacySemesterForTerm($currentTerm)) {
                Enrollment::updateOrCreate([
                    'student_id' => $student->id,
                    'semester_id' => $semester->id,
                    'subject_id' => $subject->id,
                ], [
                    'term_id' => $currentTerm->id,
                    'status' => 'active',
                ]);
            }
        });

        return back()->with('success', 'Enrolled in ' . ($subject->name ?? 'subject') . ' successfully.');
    }

    public function destroy(StudentSubjectEnrollment $enrollment)
    {
        $student = $this->getStudent();
        if (!$student || $enrollment->student_id !== $student->id) {
            return back()->with('error', 'Unauthorized action.');
        }

        if ($student->status !== 'active') {
            return back()->with('error', 'Subject registration changes are available only for active students. Contact the academic office for archived records.');
        }

        $currentTerm = $student->currentTerm;
        if (!$currentTerm || (int) $enrollment->term_id !== (int) $currentTerm->id) {
            return back()->with('error', 'Only current-term subjects can be dropped from self-service.');
        }

        if ($enrollment->status !== 'active') {
            return back()->with('error', 'Only active subject enrollments can be dropped.');
        }

        if ($enrollment->enrollment_type !== 'elective') {
            return back()->with('error', 'Compulsory subjects cannot be dropped from student self-service.');
        }

        DB::transaction(function () use ($enrollment) {
            $enrollment->update(['status' => 'dropped']);

            Enrollment::where('student_id', $enrollment->student_id)
                ->where('subject_id', $enrollment->subject_id)
                ->when($enrollment->term_id, fn($q) => $q->where('term_id', $enrollment->term_id))
                ->update(['status' => 'dropped']);
        });

        return back()->with('success', 'Subject dropped successfully.');
    }

    private function legacySemesterForTerm($term): ?Semester
    {
        return Semester::where('number', $term->term_number)
            ->orWhere('name', $term->name)
            ->orderByDesc('is_current')
            ->first();
    }
}
