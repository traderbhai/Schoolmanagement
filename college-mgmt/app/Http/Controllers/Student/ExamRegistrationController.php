<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{Exam, ExamRegistration, Attendance, FeeDemand, Semester};
use Illuminate\Support\Facades\Auth;

class ExamRegistrationController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $semester = Semester::current();
        $exams = collect();

        if ($semester && $student->program_id) {
            $exams = Exam::with(['subject','results' => fn($q) => $q->where('student_id',$student->id)])
                ->where('program_id', $student->program_id)
                ->where('exam_date', '>', now())
                ->orderBy('exam_date')
                ->get();
        }

        // Fee dues
        $hasDues = FeeDemand::where('student_id', $student->id)
            ->where('status','!=','paid')->exists();

        // Attendance eligibility per subject
        $attendancePct = [];
        $attendances = Attendance::with('timetableEntry.subject')
            ->where('student_id', $student->id)->get()
            ->groupBy(fn($a) => $a->timetableEntry?->subject_id);
        foreach ($attendances as $sid => $recs) {
            if (!$sid) continue;
            $total   = $recs->count();
            $present = $recs->whereIn('status',['present','late'])->count();
            $attendancePct[$sid] = $total > 0 ? round(($present/$total)*100) : 0;
        }

        $registrations = ExamRegistration::where('student_id', $student->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()->keyBy('exam_id');

        return view('student.exam-registration.index',
            compact('exams','registrations','hasDues','attendancePct','semester'));
    }

    public function register(Exam $exam) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $hasDues = FeeDemand::where('student_id',$student->id)->where('status','!=','paid')->exists();

        // Attendance check
        $recs = Attendance::where('student_id',$student->id)
            ->whereHas('timetableEntry', fn($q) => $q->where('subject_id',$exam->subject_id))
            ->get();
        $total   = $recs->count();
        $present = $recs->whereIn('status',['present','late'])->count();
        $attPct  = $total > 0 ? round(($present/$total)*100) : 0;
        $attEligible = $attPct >= 75;

        ExamRegistration::updateOrCreate(
            ['student_id'=>$student->id, 'exam_id'=>$exam->id],
            ['status'=>'pending', 'attendance_eligible'=>$attEligible, 'fee_cleared'=>!$hasDues]
        );

        return back()->with('success', 'Registered for ' . ($exam->subject->name ?? 'exam') . '. Exam Cell will verify eligibility.');
    }
}
