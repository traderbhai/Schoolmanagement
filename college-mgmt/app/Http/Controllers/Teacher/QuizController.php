<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\UsesOfficialTeachingSubjects;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    use UsesOfficialTeachingSubjects;

    private function activeTeacher()
    {
        return auth()->user()->teacher;
    }

    private function ensureActiveTeacher(): void
    {
        abort_unless($this->activeTeacher()?->status === 'active', 403, 'Only active teachers can manage quizzes.');
    }

    private function teacherSubjectIds(): array
    {
        return $this->officialTeachingSubjectIds();
    }

    private function ensureTeachesSubject(int $subjectId): void
    {
        abort_unless(in_array($subjectId, $this->teacherSubjectIds(), true), 403, 'You do not teach this subject.');
    }

    public function index(Request $request)
    {
        $subjectIds = $this->teacherSubjectIds();

        $quizzes = Quiz::whereIn('subject_id', $subjectIds)
            ->where('created_by', auth()->id())
            ->with(['subject', 'attempts'])
            ->when($request->filled('subject_id'), function ($query) use ($request, $subjectIds) {
                $subjectId = (int) $request->subject_id;

                return in_array($subjectId, $subjectIds, true)
                    ? $query->where('subject_id', $subjectId)
                    : $query->whereRaw('1 = 0');
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $subjects = Subject::whereIn('id', $subjectIds)->orderBy('name')->get();
        $canManageQuizzes = $this->activeTeacher()?->status === 'active';

        return view('teacher.quizzes.index', compact('quizzes', 'subjects', 'canManageQuizzes'));
    }

    public function create()
    {
        $subjects = Subject::whereIn('id', $this->teacherSubjectIds())->orderBy('name')->get();
        $currentTerm = Term::latest('start_date')->first();
        $teacher = $this->activeTeacher();
        $actionBlockedReason = match (true) {
            ! $teacher => 'Your login has the Teacher role, but no teacher profile is attached yet. Ask Admin/Academics to link your teacher profile before creating quizzes.',
            $teacher->status !== 'active' => 'Only active teachers can create quizzes.',
            $subjects->isEmpty() => 'No published teaching assignment is linked to your teacher profile yet. Quizzes can be created after your subject allocation appears in a published timetable.',
            default => null,
        };

        return view('teacher.quizzes.create', compact('subjects', 'currentTerm', 'actionBlockedReason'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'questions' => collect($request->input('questions', []))
                ->filter(fn ($question) => trim((string) ($question['question_text'] ?? '')) !== '')
                ->map(function ($question) {
                    $correctOption = (string) ($question['correct_option'] ?? '');
                    $options = collect($question['options'] ?? [])
                        ->map(fn ($option) => trim((string) $option))
                        ->filter(fn ($option) => $option !== '');
                    $originalKeys = $options->keys()->map(fn ($key) => (string) $key)->values();

                    $question['options'] = $options->values()->all();
                    $mappedCorrectOption = $originalKeys->search($correctOption);
                    $question['correct_option'] = $mappedCorrectOption === false ? null : $mappedCorrectOption;

                    return $question;
                })
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'duration_minutes' => 'nullable|integer|min:1|max:300',
            'pass_marks' => 'nullable|numeric|min:0',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'shuffle_questions' => 'boolean',
            'show_result_immediately' => 'boolean',
            'is_published' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string|max:2000',
            'questions.*.marks' => 'required|numeric|min:0.5|max:100',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*' => 'required|string|max:1000',
            'questions.*.correct_option' => 'required|integer|min:0',
        ]);

        $this->ensureActiveTeacher();
        $subjectId = (int) $data['subject_id'];
        $this->ensureTeachesSubject($subjectId);

        $questions = collect($data['questions'])->values();
        $totalMarks = $questions->sum(fn ($question) => (float) $question['marks']);

        if ($totalMarks <= 0) {
            return back()->withErrors(['questions' => 'Quiz total marks must be greater than zero.'])->withInput();
        }

        foreach ($questions as $index => $question) {
            $optionCount = count($question['options'] ?? []);
            if ((int) $question['correct_option'] >= $optionCount) {
                return back()
                    ->withErrors(["questions.{$index}.correct_option" => 'Select a valid correct option for each question.'])
                    ->withInput();
            }
        }

        if (($data['pass_marks'] ?? null) !== null && (float) $data['pass_marks'] > $totalMarks) {
            return back()->withErrors(['pass_marks' => 'Pass marks cannot exceed total marks.'])->withInput();
        }

        DB::transaction(function () use ($data, $questions, $subjectId, $totalMarks) {
            $quiz = Quiz::create([
                'subject_id' => $subjectId,
                'created_by' => auth()->id(),
                'term_id' => $this->officialTeachingTermIdForSubject($subjectId)
                    ?? Term::latest('start_date')->first()?->id,
                'title' => trim($data['title']),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'total_marks' => $totalMarks,
                'pass_marks' => $data['pass_marks'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false),
                'show_result_immediately' => (bool) ($data['show_result_immediately'] ?? true),
                'is_published' => (bool) ($data['is_published'] ?? true),
            ]);

            $questions->each(function (array $question, int $questionIndex) use ($quiz) {
                $createdQuestion = QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question_text' => trim($question['question_text']),
                    'type' => 'mcq',
                    'marks' => $question['marks'],
                    'order' => $questionIndex + 1,
                ]);

                foreach (array_values($question['options']) as $optionIndex => $optionText) {
                    QuizOption::create([
                        'quiz_question_id' => $createdQuestion->id,
                        'option_text' => trim($optionText),
                        'is_correct' => $optionIndex === (int) $question['correct_option'],
                        'order' => $optionIndex + 1,
                    ]);
                }
            });
        });

        return redirect()->route('teacher.quizzes.index')->with('success', 'Quiz created.');
    }
}
