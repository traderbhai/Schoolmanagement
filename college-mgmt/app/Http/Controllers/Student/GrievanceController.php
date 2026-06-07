<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{StudentGrievance, Student};
use Illuminate\Http\Request;

class GrievanceController extends Controller {
    private function student(): Student { return Student::where('user_id',auth()->id())->firstOrFail(); }
    public function index() {
        $student = $this->student();
        $grievances = StudentGrievance::where('student_id',$student->id)->latest()->get();
        return view('student.grievances.index', compact('grievances'));
    }
    public function create() { return view('student.grievances.create'); }
    public function store(Request $request) {
        $student = $this->student();
        $v = $request->validate(['category'=>'required|in:academic,financial,facility,faculty,administrative,other','title'=>'required|string|max:255','description'=>'required|string','priority'=>'required|in:low,normal,high,urgent']);
        StudentGrievance::create(array_merge($v,['student_id'=>$student->id,'program_id'=>$student->program_id,'status'=>'open']));
        return redirect()->route('student.grievances.index')->with('success','Grievance submitted.');
    }
    public function show(StudentGrievance $grievance) {
        abort_if($grievance->student_id !== $this->student()->id, 403);
        return view('student.grievances.show', compact('grievance'));
    }
}
