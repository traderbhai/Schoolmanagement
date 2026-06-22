<?php
namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\UsesOfficialTeachingSubjects;
use App\Models\{CourseFeedback, Subject, Term};

class FeedbackViewController extends Controller
{
    use UsesOfficialTeachingSubjects;

    public function index()
    {
        $teacher = auth()->user()->teacher;
        $currentTerm = Term::latest('start_date')->first();

        if (! $teacher) {
            $feedbackBySubject = collect();
            $profileMissing = true;

            return view('teacher.feedback.index', compact('feedbackBySubject', 'currentTerm', 'profileMissing'));
        }

        $subjectIds = $this->officialTeachingSubjectIds($teacher);

        $feedbackBySubject = Subject::whereIn('id', $subjectIds)
            ->get()
            ->map(function ($subject) use ($currentTerm) {
                $feedback = $this->enrolledFeedbackQuery($subject->id, $currentTerm)->get();

                if ($feedback->isEmpty()) {
                    $subject->feedback_stats = null;
                    return $subject;
                }

                $subject->feedback_stats = (object)[
                    'response_count' => $feedback->count(),
                    'avg_teaching' => round($feedback->whereNotNull('teaching_rating')->avg('teaching_rating'), 1),
                    'avg_content'  => round($feedback->whereNotNull('content_rating')->avg('content_rating'), 1),
                    'avg_overall'  => round($feedback->whereNotNull('overall_rating')->avg('overall_rating'), 1),
                    'comments'     => $feedback->whereNotNull('comments')->where('comments','!=','')
                                        ->pluck('comments')->take(5)->values()->all(),
                ];
                return $subject;
            });
        $profileMissing = false;

        return view('teacher.feedback.index', compact('feedbackBySubject', 'currentTerm', 'profileMissing'));
    }

    private function enrolledFeedbackQuery(int $subjectId, ?Term $currentTerm)
    {
        return CourseFeedback::where('subject_id', $subjectId)
            ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
            ->where(function ($query) use ($subjectId, $currentTerm) {
                $query->whereExists(function ($subquery) use ($subjectId, $currentTerm) {
                    $subquery->selectRaw('1')
                        ->from('student_subject_enrollments')
                        ->whereColumn('student_subject_enrollments.student_id', 'course_feedback.student_id')
                        ->where('student_subject_enrollments.subject_id', $subjectId)
                        ->where('student_subject_enrollments.status', 'active')
                        ->when($currentTerm, fn($q) => $q->where('student_subject_enrollments.term_id', $currentTerm->id));
                })->orWhereExists(function ($subquery) use ($subjectId, $currentTerm) {
                    $subquery->selectRaw('1')
                        ->from('enrollments')
                        ->whereColumn('enrollments.student_id', 'course_feedback.student_id')
                        ->where('enrollments.subject_id', $subjectId)
                        ->whereIn('enrollments.status', ['active', 'enrolled'])
                        ->when($currentTerm, fn($q) => $q->where('enrollments.term_id', $currentTerm->id));
                });
            });
    }
}
