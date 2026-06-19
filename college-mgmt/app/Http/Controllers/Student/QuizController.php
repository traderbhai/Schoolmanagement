<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    private function getStudent()
    {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        return $student;
    }

    private function ensureActiveStudent($student): void
    {
        abort_unless($student?->status === 'active', 403, 'Only active students can attempt quizzes.');
    }

    private function ensureEnrolled($student, Quiz $quiz): void
    {
        abort_unless($this->isEnrolledForQuiz($student, $quiz), 403, 'You are not enrolled in this subject.');
    }

    private function isEnrolledForQuiz($student, Quiz $quiz): bool
    {
        $canonical = $student->subjectEnrollments()
            ->where('subject_id', $quiz->subject_id)
            ->where('status', 'active')
            ->when($quiz->term_id, fn ($query) => $query->where(function ($termQuery) use ($quiz) {
                $termQuery->whereNull('term_id')->orWhere('term_id', $quiz->term_id);
            }))
            ->exists();

        if ($canonical) {
            return true;
        }

        return Enrollment::where('student_id', $student->id)
            ->where('subject_id', $quiz->subject_id)
            ->whereIn('status', ['active', 'enrolled'])
            ->when($quiz->term_id, fn ($query) => $query->where(function ($termQuery) use ($quiz) {
                $termQuery->whereNull('term_id')->orWhere('term_id', $quiz->term_id);
            }))
            ->exists();
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

    public function index()
    {
        $student = $this->getStudent();
        $subjectIds = $this->enrolledSubjectIds($student);

        $quizzes = Quiz::with('subject')
            ->whereIn('subject_id', $subjectIds)
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->get();

        $attemptMap = QuizAttempt::where('student_id', $student->id)
            ->whereIn('quiz_id', $quizzes->pluck('id'))
            ->get()
            ->keyBy('quiz_id');

        return view('student.quizzes.index', compact('quizzes', 'attemptMap'));
    }

    public function show(Quiz $quiz)
    {
        $student = $this->getStudent();
        abort_unless($quiz->is_published, 404);
        $this->ensureEnrolled($student, $quiz);

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->latest()
            ->first();

        if ($attempt && $attempt->is_completed) {
            return redirect()->route('student.quizzes.result', $quiz);
        }

        return view('student.quizzes.show', compact('quiz', 'attempt'));
    }

    public function start(Request $request, Quiz $quiz)
    {
        $student = $this->getStudent();
        $this->ensureActiveStudent($student);
        abort_unless($quiz->isActive(), 403, 'This quiz is not currently active.');
        $this->ensureEnrolled($student, $quiz);

        $existing = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('is_completed', true)
            ->exists();

        if ($existing) {
            return redirect()->route('student.quizzes.result', $quiz);
        }

        $attempt = QuizAttempt::firstOrCreate(
            ['quiz_id' => $quiz->id, 'student_id' => $student->id, 'is_completed' => false],
            ['started_at' => now()]
        );

        $questions = $quiz->questions()->with('options')->get();
        if ($quiz->shuffle_questions) {
            $questions = $questions->shuffle();
        }

        return view('student.quizzes.attempt', compact('quiz', 'attempt', 'questions'));
    }

    public function submitAttempt(Request $request, Quiz $quiz)
    {
        $student = $this->getStudent();
        $this->ensureActiveStudent($student);
        abort_unless($quiz->isActive(), 403, 'This quiz is not currently active.');
        $this->ensureEnrolled($student, $quiz);

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('is_completed', false)
            ->firstOrFail();

        if ($quiz->duration_minutes && $attempt->started_at && now()->gt($attempt->started_at->copy()->addMinutes((int) $quiz->duration_minutes))) {
            abort(403, 'This quiz attempt has exceeded the allowed duration.');
        }

        $answers = $request->input('answers', []);
        $score = 0;

        DB::transaction(function () use ($attempt, $quiz, $answers, &$score) {
            foreach ($quiz->questions as $question) {
                $selectedOptionId = $answers[$question->id] ?? null;
                $isCorrect = false;

                if ($question->type === 'mcq' && $selectedOptionId) {
                    $selectedOption = $question->options()->where('id', $selectedOptionId)->first();
                    if (! $selectedOption) {
                        $selectedOptionId = null;
                    } else {
                        $isCorrect = (bool) $selectedOption->is_correct;
                        if ($isCorrect) $score += $question->marks;
                    }
                }

                QuizAnswer::create([
                    'quiz_attempt_id'  => $attempt->id,
                    'quiz_question_id' => $question->id,
                    'quiz_option_id'   => $selectedOptionId ?: null,
                    'is_correct'       => $isCorrect,
                ]);
            }

            $attempt->update([
                'submitted_at' => now(),
                'score'        => $score,
                'is_completed' => true,
            ]);
        });

        return redirect()->route('student.quizzes.result', $quiz)
            ->with('success', 'Quiz submitted!');
    }

    public function result(Quiz $quiz)
    {
        $student = $this->getStudent();
        $this->ensureEnrolled($student, $quiz);
        abort_unless($quiz->is_published, 404);

        if (! $quiz->show_result_immediately && $quiz->isActive()) {
            return redirect()->route('student.quizzes.index')
                ->with('error', 'Quiz results are not available yet.');
        }

        $attempt = QuizAttempt::with(['answers.question', 'answers.option'])
            ->where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('is_completed', true)
            ->firstOrFail();

        return view('student.quizzes.result', compact('quiz', 'attempt'));
    }
}
