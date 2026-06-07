<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{StudentGrievance, GrievanceComment, Student};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GrievanceController extends Controller {
    private function student(): Student { return Student::where('user_id', Auth::id())->firstOrFail(); }

    public function index() {
        $student = $this->student();
        $grievances = StudentGrievance::where('student_id', $student->id)->latest()->get();
        return view('student.grievances.index', compact('grievances'));
    }

    public function create() { return view('student.grievances.create'); }

    public function store(Request $request) {
        $student = $this->student();
        $v = $request->validate([
            'category'    => 'required|in:academic,financial,facility,faculty,administrative,other',
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'priority'    => 'required|in:low,normal,high,urgent',
        ]);
        StudentGrievance::create(array_merge($v, [
            'student_id' => $student->id,
            'program_id' => $student->program_id,
            'status'     => 'open',
        ]));
        return redirect()->route('student.grievances.index')->with('success', 'Grievance submitted.');
    }

    public function show(StudentGrievance $grievance) {
        $student = $this->student();
        abort_if($grievance->student_id !== $student->id, 403);
        $grievance->load('comments.user');
        return view('student.grievances.show', compact('grievance'));
    }

    public function addComment(Request $request, StudentGrievance $grievance) {
        $student = $this->student();
        abort_if($grievance->student_id !== $student->id, 403);
        abort_unless($grievance->isOpen(), 422, 'Cannot comment on a closed grievance.');

        $request->validate(['comment' => 'required|string|max:2000']);
        GrievanceComment::create([
            'student_grievance_id' => $grievance->id,
            'user_id'              => Auth::id(),
            'comment'              => $request->comment,
        ]);
        return back()->with('success', 'Comment added.');
    }

    public function close(StudentGrievance $grievance) {
        $student = $this->student();
        abort_if($grievance->student_id !== $student->id, 403);
        $grievance->update(['status' => 'resolved']);
        return back()->with('success', 'Grievance closed.');
    }
}
