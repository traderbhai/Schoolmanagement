<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{
    private function getStudent()
    {
        $student = Auth::user()->student;
        abort_unless($student, 403, 'Student record not found.');
        return $student;
    }

    private function ensureActiveStudent($student): void
    {
        abort_unless($student?->status === 'active', 403, 'Only active students can submit coursework.');
    }

    private function isEnrolledForAssignment($student, Assignment $assignment): bool
    {
        $canonical = $student->subjectEnrollments()
            ->where('subject_id', $assignment->subject_id)
            ->where('status', 'active')
            ->when($assignment->term_id, fn ($query) => $query->where(function ($termQuery) use ($assignment) {
                $termQuery->whereNull('term_id')->orWhere('term_id', $assignment->term_id);
            }))
            ->exists();

        if ($canonical) {
            return true;
        }

        return Enrollment::where('student_id', $student->id)
            ->where('subject_id', $assignment->subject_id)
            ->whereIn('status', ['active', 'enrolled'])
            ->when($assignment->term_id, fn ($query) => $query->where(function ($termQuery) use ($assignment) {
                $termQuery->whereNull('term_id')->orWhere('term_id', $assignment->term_id);
            }))
            ->exists();
    }

    private function ensureEnrolled($student, Assignment $assignment): void
    {
        abort_unless($this->isEnrolledForAssignment($student, $assignment), 403, 'You are not enrolled in this subject.');
    }

    private function enrolledSubjectIds($student)
    {
        $canonical = $student->subjectEnrollments()
            ->where('status', 'active')
            ->pluck('subject_id');

        $legacy = Enrollment::where('student_id', $student->id)
            ->whereIn('status', ['active', 'enrolled'])
            ->pluck('subject_id');

        return $canonical->merge($legacy)->unique()->values();
    }

    public function index(Request $request)
    {
        $student = $this->getStudent();

        $subjectIds = $this->enrolledSubjectIds($student);

        $assignments = Assignment::with(['subject', 'submissions' => fn($q) => $q->where('student_id', $student->id)])
            ->whereIn('subject_id', $subjectIds)
            ->where('is_published', true)
            ->orderBy('due_at')
            ->get();

        $now = now();
        $upcoming = $assignments->filter(fn($a) => $a->due_at->isFuture());
        $past = $assignments->filter(fn($a) => $a->due_at->isPast());
        $filterSummary = 'All published assignments in your enrolled subjects';

        if ($request->query('filter') === 'pending_next_7') {
            $upcoming = $upcoming
                ->filter(function ($assignment) use ($now) {
                    $submission = $assignment->submissions->first();

                    return $assignment->due_at <= $now->copy()->addDays(7)
                        && (! $submission || ! in_array($submission->status, ['submitted', 'graded'], true));
                })
                ->values();
            $past = collect();
            $filterSummary = 'Pending assignments due in the next 7 days';
        }

        return view('student.assignments.index', compact('upcoming', 'past', 'now', 'filterSummary'));
    }

    public function show(Assignment $assignment)
    {
        $student = $this->getStudent();
        abort_unless($assignment->is_published, 404);
        $this->ensureEnrolled($student, $assignment);

        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        return view('student.assignments.show', compact('assignment', 'submission'));
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $student = $this->getStudent();
        $this->ensureActiveStudent($student);
        abort_unless($assignment->is_published, 403);
        $this->ensureEnrolled($student, $assignment);

        if (! $assignment->allow_late_submission && $assignment->due_at->isPast()) {
            return back()->with('error', 'Submission deadline has passed.');
        }

        $existingSubmission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existingSubmission && $existingSubmission->status === 'graded') {
            return back()->with('error', 'This assignment has already been graded and cannot be resubmitted.');
        }

        $request->validate([
            'answer_text' => 'nullable|string',
            'file'        => 'nullable|file|max:10240',
        ]);

        $hasAnswerText = trim((string) $request->input('answer_text', '')) !== '';
        $hasExistingFile = (bool) ($existingSubmission?->file_path);
        if (! $hasAnswerText && ! $request->hasFile('file') && ! $hasExistingFile) {
            return back()->withErrors(['answer_text' => 'Enter an answer or attach a file before submitting.']);
        }

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store("assignments/{$assignment->id}", 'local');
        }

        $isLate = $assignment->due_at->isPast();

        $submissionData = [
            'answer_text' => $request->answer_text,
            'submitted_at' => now(),
            'is_late'     => $isLate,
            'status'      => 'submitted',
        ];

        if ($filePath !== null || ! $existingSubmission) {
            $submissionData['file_path'] = $filePath;
        }

        AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            $submissionData
        );

        return redirect()->route('student.assignments.show', $assignment)
            ->with('success', 'Assignment submitted successfully.');
    }
}
