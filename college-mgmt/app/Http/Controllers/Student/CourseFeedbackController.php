<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{CourseFeedback, Subject};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseFeedbackController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $subjects = $student->subjects()->with('teachers')->get();
        $submitted = CourseFeedback::where('student_id', $student->id)
            ->pluck('subject_id')->toArray();

        return view('student.feedback.index', compact('subjects','submitted'));
    }

    public function create(Subject $subject) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $alreadySubmitted = CourseFeedback::where('student_id',$student->id)
            ->where('subject_id',$subject->id)->exists();

        if ($alreadySubmitted) {
            return redirect()->route('student.feedback.index')
                ->with('info', 'You have already submitted feedback for ' . $subject->name);
        }

        return view('student.feedback.create', compact('subject'));
    }

    public function store(Request $request, Subject $subject) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $data = $request->validate([
            'teaching_rating' => 'required|integer|min:1|max:5',
            'content_rating'  => 'required|integer|min:1|max:5',
            'overall_rating'  => 'required|integer|min:1|max:5',
            'comments'        => 'nullable|string|max:1000',
        ]);

        CourseFeedback::updateOrCreate(
            ['student_id'=>$student->id,'subject_id'=>$subject->id],
            array_merge($data, ['term_id'=>$student->current_term_id,'is_anonymous'=>true])
        );

        return redirect()->route('student.feedback.index')
            ->with('success', 'Feedback submitted for ' . $subject->name . '. Thank you!');
    }
}
