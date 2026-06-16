<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\{Exam, ExamResult, Student, TimetableEntry, Semester};
use Illuminate\Http\Request;

class ExamController extends Controller
{
    private function ensureTeacherForExam(Exam $exam): void
    {
        $teacher = auth()->user()->teacher;
        abort_unless($teacher, 403);

        $teaches = TimetableEntry::where('teacher_id', $teacher->id)
            ->where('subject_id', $exam->subject_id)
            ->where('is_active', true)
            ->exists();
        abort_unless($teaches, 403, 'You do not teach this subject.');
    }

    private function enrolledStudentIdsForExam(Exam $exam)
    {
        return Student::whereHas('enrollments', fn($q) =>
            $q->where('semester_id', $exam->semester_id)
              ->where('subject_id', $exam->subject_id)
              ->where('status', 'active')
        )->pluck('id');
    }

    public function index()
    {
        $teacher = auth()->user()->teacher;
        // Subjects this teacher teaches (via timetable entries)
        $subjectIds = TimetableEntry::where('teacher_id', $teacher->id)
            ->where('is_active', true)->pluck('subject_id')->unique();

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

        $students = Student::whereHas('enrollments', fn($q) =>
            $q->where('semester_id', $exam->semester_id)
              ->where('subject_id', $exam->subject_id)
              ->where('status', 'active')
        )->with(['user',
            'examResults' => fn($q) => $q->where('exam_id', $exam->id)
        ])->orderBy('roll_number')->get();

        return view('teacher.exams.results', compact('exam', 'students'));
    }

    public function saveResults(Request $request, Exam $exam)
    {
        $this->ensureTeacherForExam($exam);

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
