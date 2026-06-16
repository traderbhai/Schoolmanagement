<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Enrollment, Student, Semester, Subject, Course, StudentSubjectEnrollment, Term};
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
        $data = $request->validate([
            'student_id'  => 'required|exists:students,id',
            'semester_id' => 'required|exists:semesters,id',
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $created = 0;
        $student = Student::findOrFail($data['student_id']);
        $termId = $this->termIdForStudentSemester($student, (int) $data['semester_id']);
        if (! $termId) {
            return back()->withErrors(['semester_id' => 'Selected semester is not mapped to this student program and batch term.'])->withInput();
        }

        foreach ($data['subject_ids'] as $subjectId) {
            $subject = $this->eligibleSubjectForStudentTerm($student, (int) $subjectId, $termId);
            if (! $subject) {
                return back()->withErrors(['subject_ids' => 'Selected subjects must be active and belong to the student program and selected term.'])->withInput();
            }

            if ($this->hasCompletedCanonicalHistory($student->id, (int) $subjectId, $termId)) {
                return back()->withErrors(['subject_ids' => 'Completed subject history cannot be reactivated from enrollment management.'])->withInput();
            }

            $enrollment = Enrollment::firstOrCreate(
                ['student_id' => $student->id, 'semester_id' => $data['semester_id'], 'subject_id' => $subjectId],
                ['status' => 'active', 'term_id' => $termId]
            );
            $enrollment->forceFill(['status' => 'active', 'term_id' => $termId])->save();
            $this->syncCanonicalEnrollment($student, $subject, $termId);
            $created++;
        }

        return redirect()->route('admin.enrollments.index')->with('success', "$created subject(s) enrolled successfully.");
    }

    public function bulkEnroll(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|exists:courses,id',
            'semester_id' => 'required|exists:semesters,id',
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $students = Student::where('course_id', $data['course_id'])->where('status', 'active')->get();
        $count = 0;
        foreach ($students as $student) {
            $termId = $this->termIdForStudentSemester($student, (int) $data['semester_id']);
            if (! $termId) {
                continue;
            }

            foreach ($data['subject_ids'] as $subjectId) {
                $subject = $this->eligibleSubjectForStudentTerm($student, (int) $subjectId, $termId);
                if (! $subject || $this->hasCompletedCanonicalHistory($student->id, (int) $subjectId, $termId)) {
                    continue;
                }

                $enrollment = Enrollment::firstOrCreate(
                    ['student_id' => $student->id, 'semester_id' => $data['semester_id'], 'subject_id' => $subjectId],
                    ['status' => 'active', 'term_id' => $termId]
                );
                $enrollment->forceFill(['status' => 'active', 'term_id' => $termId])->save();
                $this->syncCanonicalEnrollment($student, $subject, $termId);
                $count++;
            }
        }

        if ($count === 0) {
            return back()->with('error', 'No eligible enrollment rows were created. Check semester-term mapping, subject program/term, and completed history.');
        }

        return redirect()->route('admin.enrollments.index')->with('success', "Bulk enrollment: $count records created for {$students->count()} students.");
    }

    public function destroy(Enrollment $enrollment)
    {
        if ($enrollment->status === 'completed') {
            return back()->with('error', 'Completed enrollment history cannot be deleted.');
        }

        if ($enrollment->term_id) {
            StudentSubjectEnrollment::where('student_id', $enrollment->student_id)
                ->where('subject_id', $enrollment->subject_id)
                ->where('term_id', $enrollment->term_id)
                ->update(['status' => 'dropped']);
        }

        $enrollment->delete();
        return back()->with('success', 'Enrollment removed.');
    }

    private function syncCanonicalEnrollment(Student $student, Subject $subject, ?int $termId): void
    {
        if (! $termId) {
            return;
        }

        $enrollmentType = $subject && $subject->type === 'elective' ? 'elective' : 'compulsory';

        StudentSubjectEnrollment::updateOrCreate(
            [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'term_id' => $termId,
            ],
            [
                'enrollment_type' => $enrollmentType,
                'status' => 'active',
            ]
        );
    }

    private function eligibleSubjectForStudentTerm(Student $student, int $subjectId, int $termId): ?Subject
    {
        $term = Term::find($termId);
        if (! $term) {
            return null;
        }

        return Subject::where('id', $subjectId)
            ->where('is_active', true)
            ->where('program_id', $student->program_id)
            ->where(function ($query) use ($term) {
                $query->whereNull('term_number')
                    ->orWhere('term_number', $term->term_number);
            })
            ->first();
    }

    private function hasCompletedCanonicalHistory(int $studentId, int $subjectId, int $termId): bool
    {
        return StudentSubjectEnrollment::where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->where('status', 'completed')
            ->exists();
    }

    private function termIdForStudentSemester(Student $student, int $semesterId): ?int
    {
        $semester = Semester::find($semesterId);
        if (! $semester) {
            return null;
        }

        return Term::query()
            ->when($student->program_id, fn($q) => $q->where('program_id', $student->program_id))
            ->when($student->batch_id, fn($q) => $q->where('batch_id', $student->batch_id))
            ->where(fn($q) => $q->where('term_number', $semester->number)->orWhere('name', $semester->name))
            ->value('id');
    }
}
