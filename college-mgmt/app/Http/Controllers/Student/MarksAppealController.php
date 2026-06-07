<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{MarksAppeal, ExamResult};
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
        $results = ExamResult::with('exam.subject')
            ->where('student_id', $student->id)
            ->where('is_absent', false)
            ->orderByDesc('id')->get();
        return view('student.appeals.create', compact('results'));
    }

    public function store(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $data = $request->validate([
            'exam_result_id' => 'required|exists:exam_results,id',
            'reason'         => 'required|string|max:255',
            'description'    => 'required|string|max:2000',
            'marks_claimed'  => 'nullable|numeric|min:0',
        ]);

        // Ensure result belongs to this student
        $result = ExamResult::where('id', $data['exam_result_id'])
            ->where('student_id', $student->id)->firstOrFail();

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
}
