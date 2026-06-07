<?php
namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{CourseFeedback, Subject, Term, TimetableEntry};

class FeedbackViewController extends Controller
{
    public function index()
    {
        $teacher = auth()->user()->teacher;
        if (!$teacher) abort(403);

        $subjectIds = TimetableEntry::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->pluck('subject_id')->unique()->toArray();

        $currentTerm = Term::latest('start_date')->first();

        $feedbackBySubject = Subject::whereIn('id', $subjectIds)
            ->get()
            ->map(function ($subject) use ($currentTerm) {
                $feedback = CourseFeedback::where('subject_id', $subject->id)
                    ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
                    ->get();

                if ($feedback->isEmpty()) {
                    $subject->feedback_stats = null;
                    return $subject;
                }

                $subject->feedback_stats = (object)[
                    'count'        => $feedback->count(),
                    'avg_teaching' => round($feedback->whereNotNull('teaching_rating')->avg('teaching_rating'), 1),
                    'avg_content'  => round($feedback->whereNotNull('content_rating')->avg('content_rating'), 1),
                    'avg_overall'  => round($feedback->whereNotNull('overall_rating')->avg('overall_rating'), 1),
                    'comments'     => $feedback->whereNotNull('comments')->where('comments','!=','')
                                        ->pluck('comments')->take(5),
                ];
                return $subject;
            });

        return view('teacher.feedback.index', compact('feedbackBySubject', 'currentTerm'));
    }
}
