<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
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

    public function index()
    {
        $student = $this->getStudent();
        $subjectIds = $student->subjects()->pluck('subjects.id');

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
        abort_unless($quiz->isActive(), 403, 'This quiz is not currently active.');

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

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('is_completed', false)
            ->firstOrFail();

        $answers = $request->input('answers', []);
        $score = 0;

        DB::transaction(function () use ($attempt, $quiz, $answers, &$score) {
            foreach ($quiz->questions as $question) {
                $selectedOptionId = $answers[$question->id] ?? null;
                $isCorrect = false;

                if ($question->type === 'mcq' && $selectedOptionId) {
                    $isCorrect = $question->options()
                        ->where('id', $selectedOptionId)
                        ->where('is_correct', true)
                        ->exists();
                    if ($isCorrect) $score += $question->marks;
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

        $attempt = QuizAttempt::with(['answers.question', 'answers.option'])
            ->where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('is_completed', true)
            ->firstOrFail();

        return view('student.quizzes.result', compact('quiz', 'attempt'));
    }
}
