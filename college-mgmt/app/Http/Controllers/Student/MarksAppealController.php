<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{MarksAppeal, ExamResult, Student};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarksAppealController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $appeals = MarksAppeal::with(['examResult.exam.subject'])
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')->paginate(15);
        return view('student.appeals.index', compact('appeals'));
    }

    public function create() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $results = $this->appealableResults($student)->orderByDesc('id')->get();
        return view('student.appeals.create', compact('results'));
    }

    public function store(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $data = $request->validate([
            'exam_result_id' => 'required|exists:exam_results,id',
            'reason'         => 'required|string|max:255',
            'description'    => 'required|string|max:2000',
            'marks_claimed'  => 'required|numeric|min:0',
        ]);

        $result = $this->appealableResults($student)
            ->where('id', $data['exam_result_id'])
            ->firstOrFail();

        if ($result->exam?->total_marks !== null && (float) $data['marks_claimed'] > (float) $result->exam->total_marks) {
            return back()
                ->withErrors(['marks_claimed' => 'Claimed marks cannot exceed the exam total marks.'])
                ->withInput();
        }

        // Only one appeal per result
        abort_if(
            MarksAppeal::where('student_id',$student->id)
                ->where('exam_result_id',$result->id)->exists(),
            422, 'You have already submitted an appeal for this result.'
        );

        MarksAppeal::create(array_merge($data, ['student_id'=>$student->id]));
        return redirect()->route('student.appeals.index')
            ->with('success', 'Marks appeal submitted. The Exam Cell will review it.');
    }

    private function appealableResults(Student $student)
    {
        return ExamResult::with('exam.subject')
            ->where('student_id', $student->id)
            ->where('is_absent', false)
            ->whereHas('exam', function ($examQuery) use ($student) {
                $examQuery->whereNotNull('published_at')
                    ->where(function ($eligibleExam) use ($student) {
                    $eligibleExam->whereExists(function ($subquery) use ($student) {
                        $subquery->selectRaw('1')
                            ->from('student_subject_enrollments')
                            ->where('student_subject_enrollments.student_id', $student->id)
                            ->whereColumn('student_subject_enrollments.subject_id', 'exams.subject_id')
                            ->where('student_subject_enrollments.status', 'active')
                            ->where(function ($termQuery) {
                                $termQuery->whereNull('exams.term_id')
                                    ->orWhereNull('student_subject_enrollments.term_id')
                                    ->orWhereColumn('student_subject_enrollments.term_id', 'exams.term_id');
                            });
                    })->orWhereExists(function ($subquery) use ($student) {
                        $subquery->selectRaw('1')
                            ->from('enrollments')
                            ->where('enrollments.student_id', $student->id)
                            ->whereColumn('enrollments.subject_id', 'exams.subject_id')
                            ->whereIn('enrollments.status', ['active', 'enrolled'])
                            ->where(function ($termQuery) {
                                $termQuery->where(function ($q) {
                                    $q->whereNotNull('exams.term_id')
                                        ->whereColumn('enrollments.term_id', 'exams.term_id');
                                })->orWhere(function ($q) {
                                    $q->whereNull('exams.term_id')
                                        ->whereColumn('enrollments.semester_id', 'exams.semester_id');
                                })->orWhereNull('enrollments.term_id');
                            });
                    });
                });
            });
    }
}
