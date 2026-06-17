<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{CourseFeedback, Enrollment, Student, StudentSubjectEnrollment, Subject};
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseFeedbackController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $subjects = $this->enrolledSubjectsForFeedback($student);
        $canSubmitFeedback = $student->status === 'active';

        return view('student.feedback.index', compact('subjects', 'canSubmitFeedback'));
    }

    public function create(Subject $subject) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        if ($student->status !== 'active') {
            return redirect()->route('student.feedback.index')
                ->with('error', 'Course feedback can be submitted only by active students.');
        }

        $termId = $this->feedbackTermId($student, $subject);
        abort_unless($this->isSubjectEnrolled($student, $subject), 403);

        $alreadySubmitted = CourseFeedback::where('student_id',$student->id)
            ->where('subject_id',$subject->id)
            ->where(function ($query) use ($termId) {
                $termId
                    ? $query->where('term_id', $termId)
                    : $query->whereNull('term_id');
            })
            ->exists();

        if ($alreadySubmitted) {
            return redirect()->route('student.feedback.index')
                ->with('info', 'You have already submitted feedback for ' . $subject->name);
        }

        return view('student.feedback.create', compact('subject'));
    }

    public function store(Request $request, Subject $subject) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        if ($student->status !== 'active') {
            return redirect()->route('student.feedback.index')
                ->with('error', 'Course feedback can be submitted only by active students.');
        }

        $termId = $this->feedbackTermId($student, $subject);
        abort_unless($this->isSubjectEnrolled($student, $subject), 403);

        $alreadySubmitted = CourseFeedback::where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->where(function ($query) use ($termId) {
                $termId
                    ? $query->where('term_id', $termId)
                    : $query->whereNull('term_id');
            })
            ->exists();

        if ($alreadySubmitted) {
            return redirect()->route('student.feedback.index')
                ->with('info', 'You have already submitted feedback for ' . $subject->name);
        }

        $data = $request->validate([
            'teaching_rating' => 'required|integer|min:1|max:5',
            'content_rating'  => 'required|integer|min:1|max:5',
            'overall_rating'  => 'required|integer|min:1|max:5',
            'comments'        => 'nullable|string|max:1000',
        ]);

        CourseFeedback::create(array_merge($data, [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $termId,
            'is_anonymous' => true,
        ]));

        return redirect()->route('student.feedback.index')
            ->with('success', 'Feedback submitted for ' . $subject->name . '. Thank you!');
    }

    private function enrolledSubjectsForFeedback(Student $student): Collection
    {
        $termBySubject = [];

        StudentSubjectEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN term_id = ? THEN 0 ELSE 1 END', [$student->current_term_id ?? 0])
            ->get(['subject_id', 'term_id'])
            ->each(function (StudentSubjectEnrollment $enrollment) use (&$termBySubject) {
                $termBySubject[$enrollment->subject_id] ??= $enrollment->term_id;
            });

        Enrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['active', 'enrolled'])
            ->get(['subject_id', 'term_id'])
            ->each(function (Enrollment $enrollment) use (&$termBySubject) {
                $termBySubject[$enrollment->subject_id] ??= $enrollment->term_id;
            });

        if ($termBySubject === []) {
            return collect();
        }

        return Subject::query()
            ->whereIn('id', array_keys($termBySubject))
            ->orderBy('name')
            ->get()
            ->map(function (Subject $subject) use ($student, $termBySubject) {
                $termId = $termBySubject[$subject->id] ?? null;
                $subject->feedback_term_id = $termId;
                $subject->feedback_submitted = CourseFeedback::where('student_id', $student->id)
                    ->where('subject_id', $subject->id)
                    ->where(function ($query) use ($termId) {
                        $termId
                            ? $query->where('term_id', $termId)
                            : $query->whereNull('term_id');
                    })
                    ->exists();

                return $subject;
            });
    }

    private function isSubjectEnrolled(Student $student, Subject $subject): bool
    {
        return $this->feedbackTermId($student, $subject) !== false;
    }

    private function feedbackTermId(Student $student, Subject $subject): int|null|false
    {
        $canonical = StudentSubjectEnrollment::query()
            ->where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN term_id = ? THEN 0 ELSE 1 END', [$student->current_term_id ?? 0])
            ->first(['term_id']);

        if ($canonical) {
            return $canonical->term_id;
        }

        $legacy = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->whereIn('status', ['active', 'enrolled'])
            ->first(['term_id']);

        return $legacy ? $legacy->term_id : false;
    }
}
