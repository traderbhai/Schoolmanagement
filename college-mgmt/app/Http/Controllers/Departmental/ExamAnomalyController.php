<?php
namespace App\Http\Controllers\Departmental;
use App\Http\Controllers\Controller;
use App\Models\{ExamAnomalyLog, Exam, Student};
use Illuminate\Http\Request;

class ExamAnomalyController extends Controller {
    public function index(Request $request) {
        $query = ExamAnomalyLog::with(['exam.subject','student.user','reportedBy'])->latest();
        if ($request->filled('severity')) $query->where('severity',$request->severity);
        if ($request->filled('anomaly_type')) $query->where('anomaly_type',$request->anomaly_type);
        $logs = $query->paginate(20)->withQueryString();
        return view('departmental.exam-anomalies.index', compact('logs'));
    }
    public function create(Request $request) {
        $exams = Exam::with(['subject','program'])->latest()->take(50)->get();
        $students = Student::with('user')->where('status','active')->take(100)->get();
        return view('departmental.exam-anomalies.create', compact('exams','students'));
    }
    public function store(Request $request) {
        $v = $request->validate([
            'exam_id'=>'required|exists:exams,id',
            'student_id'=>'required|exists:students,id',
            'anomaly_type'=>'required|in:malpractice,absent_without_reason,late_entry,paper_leak,other',
            'description'=>'required|string',
            'severity'=>'required|in:low,medium,high,critical',
            'action_taken'=>'nullable|in:warning,debarred,cancelled,none',
        ]);
        ExamAnomalyLog::create(array_merge($v,['reported_by'=>auth()->id()]));
        return redirect()->route('exam-cell.anomalies.index')->with('success','Anomaly logged.');
    }
    public function show(ExamAnomalyLog $anomalyLog) {
        $anomalyLog->load(['exam.subject','student.user','reportedBy','resolvedBy']);
        return view('departmental.exam-anomalies.show', compact('anomalyLog'));
    }
    public function resolve(Request $request, ExamAnomalyLog $anomalyLog) {
        $request->validate(['action_taken'=>'required|in:warning,debarred,cancelled,none']);
        $anomalyLog->update(['action_taken'=>$request->action_taken,'resolved_by'=>auth()->id(),'resolved_at'=>now()]);
        return back()->with('success','Anomaly resolved.');
    }
}
