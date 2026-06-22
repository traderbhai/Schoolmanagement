<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\UsesOfficialTeachingSubjects;
use App\Models\{Exam, ExamResult, Student, Semester};
use Illuminate\Http\Request;

class ExamController extends Controller
{
    use UsesOfficialTeachingSubjects;

    private function ensureTeacherForExam(Exam $exam): void
    {
        $teacher = auth()->user()->teacher;
        abort_unless($teacher, 403);

        $teaches = $this->teachesOfficialSubject(
            (int) $exam->subject_id,
            $exam->program_id ? (int) $exam->program_id : null,
            $exam->term_id ? (int) $exam->term_id : null,
            $exam->semester_id ? (int) $exam->semester_id : null,
            $teacher
        );
        abort_unless($teaches, 403, 'You do not teach this exam subject for the selected program and term.');
    }

    private function ensureActiveTeacher(): void
    {
        abort_unless(auth()->user()->teacher?->status === 'active', 403, 'Only active teachers can save exam results.');
    }

    private function enrolledStudentIdsForExam(Exam $exam)
    {
        return $this->studentRosterQueryForExam($exam)->pluck('id');
    }

    private function studentRosterQueryForExam(Exam $exam)
    {
        return Student::query()
            ->when($exam->program_id, fn ($query) => $query->where('program_id', $exam->program_id))
            ->where(function ($query) use ($exam) {
                $query->whereHas('subjectEnrollments', function ($enrollmentQuery) use ($exam) {
                    $enrollmentQuery->where('subject_id', $exam->subject_id)
                        ->where('status', 'active')
                        ->when($exam->term_id, fn ($termQuery) => $termQuery->where('term_id', $exam->term_id));
                })
                ->orWhereHas('enrollments', function ($enrollmentQuery) use ($exam) {
                    $enrollmentQuery->where('subject_id', $exam->subject_id)
                        ->whereIn('status', ['enrolled', 'active'])
                        ->when(
                            $exam->semester_id,
                            fn ($semesterQuery) => $semesterQuery->where('semester_id', $exam->semester_id),
                            fn ($termQuery) => $termQuery->when($exam->term_id, fn ($query) => $query->where('term_id', $exam->term_id))
                        );
                });
            });
    }

    public function index()
    {
        $teacher = auth()->user()->teacher;
        if (! $teacher) {
            $semesters = Semester::orderByDesc('id')->get();
            $exams = collect();

            return view('teacher.exams.index', compact('exams', 'semesters'))
                ->with('warning', 'Your teacher profile is not linked yet. Exams will appear after the profile is assigned.');
        }

        $subjectIds = $this->officialTeachingSubjectIds($teacher);

        $semesters = Semester::orderByDesc('id')->get();
        $exams = Exam::whereIn('subject_id', $subjectIds)
            ->with(['subject', 'semester'])
            ->orderByDesc('exam_date')
            ->get();

        return view('teacher.exams.index', compact('exams', 'semesters'));
    }

    public function enterResults(Exam $exam)
    {
        $this->ensureTeacherForExam($exam);

        $students = $this->studentRosterQueryForExam($exam)->with(['user',
            'examResults' => fn($q) => $q->where('exam_id', $exam->id)
        ])->orderBy('roll_number')->get();
        $canSaveResults = auth()->user()->teacher?->status === 'active';

        return view('teacher.exams.results', compact('exam', 'students', 'canSaveResults'));
    }

    public function saveResults(Request $request, Exam $exam)
    {
        $this->ensureTeacherForExam($exam);
        $this->ensureActiveTeacher();

        if ($exam->published_at) {
            return back()->with('error', 'Published results are locked. Contact Exam Cell for appeal or correction workflow.');
        }

        if ($exam->exam_date && $exam->exam_date->isFuture()) {
            return back()->with('error', 'Exam results cannot be entered before the exam date.');
        }

        $request->validate([
            'results' => 'required|array',
            'results.*.is_absent' => 'nullable|boolean',
            'results.*.marks_obtained' => 'nullable|numeric|min:0|max:' . (float) $exam->total_marks,
            'results.*.remarks' => 'nullable|string|max:500',
        ]);

        $allowedStudentIds = $this->enrolledStudentIdsForExam($exam)->map(fn ($id) => (string) $id)->all();
        $submittedStudentIds = array_map('strval', array_keys($request->results));
        abort_unless(empty(array_diff($submittedStudentIds, $allowedStudentIds)), 403, 'Results include students outside this exam subject.');

        foreach ($request->results as $studentId => $result) {
            $isAbsent = !empty($result['is_absent']);
            if (! $isAbsent && ! array_key_exists('marks_obtained', $result)) {
                return back()
                    ->withInput()
                    ->withErrors(["results.{$studentId}.marks_obtained" => 'Marks are required unless the student is marked absent.']);
            }

            ExamResult::updateOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $studentId],
                [
                    'marks_obtained' => $isAbsent ? null : ($result['marks_obtained'] ?? null),
                    'is_absent'      => $isAbsent,
                    'remarks'        => $result['remarks'] ?? null,
                ]
            );
        }

        return redirect()->route('teacher.exams.index')->with('success', 'Results saved.');
    }
}
