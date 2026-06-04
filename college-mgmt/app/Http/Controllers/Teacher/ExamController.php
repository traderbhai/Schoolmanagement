<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\{Exam, ExamResult, Student, TimetableEntry, Semester};
use Illuminate\Http\Request;

class ExamController extends Controller
{
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
        $teacher = auth()->user()->teacher;
        // Verify teacher teaches this subject
        $teaches = TimetableEntry::where('teacher_id', $teacher->id)
            ->where('subject_id', $exam->subject_id)->exists();
        abort_unless($teaches, 403, 'You do not teach this subject.');

        $students = Student::whereHas('enrollments', fn($q) =>
            $q->where('semester_id', $exam->semester_id)
              ->where('subject_id', $exam->subject_id)
        )->with(['user',
            'examResults' => fn($q) => $q->where('exam_id', $exam->id)
        ])->orderBy('roll_number')->get();

        return view('teacher.exams.results', compact('exam', 'students'));
    }

    public function saveResults(Request $request, Exam $exam)
    {
        $teacher = auth()->user()->teacher;
        $teaches = TimetableEntry::where('teacher_id', $teacher->id)
            ->where('subject_id', $exam->subject_id)->exists();
        abort_unless($teaches, 403);

        foreach ($request->results as $studentId => $result) {
            $isAbsent = !empty($result['is_absent']);
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
