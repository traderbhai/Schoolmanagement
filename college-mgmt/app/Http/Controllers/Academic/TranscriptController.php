<?php
namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\{Student, AcademicTranscript, Term, ExamResult, Exam};
use App\Services\GradeService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class TranscriptController extends Controller {

    public function index(Request $request) {
        $query = Student::with(['user','program','batch'])->where('status','active');
        if ($request->filled('program_id')) $query->where('program_id',$request->program_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->whereHas('user', fn($q) => $q->where('name','like',"%$s%")->orWhere('email','like',"%$s%"))
                  ->orWhere('enrollment_number','like',"%$s%");
            });
        }
        $students = $query->paginate(25)->withQueryString();
        $programs = \App\Models\Program::where('is_active',true)->orderBy('name')->get();
        return view('academic.transcripts.index', compact('students','programs'));
    }

    public function show(Student $student) {
        $student->load(['user','program','batch']);
        $terms = Term::whereHas('batch', fn($q) => $q->where('id',$student->batch_id))
            ->orderBy('term_number')->get();

        $gradeService = new GradeService();
        $semesterReports = [];
        $totalCredits = 0;
        $earnedPoints = 0.0;

        foreach ($terms as $term) {
            $exams = Exam::where('program_id', $student->program_id)
                ->where('term_id', $term->id)
                ->with('subject')
                ->get();
            $termTotalCredits = 0;
            $termEarnedCredits = 0;
            $termEarnedPoints = 0.0;
            $subjects = [];

            foreach ($exams as $exam) {
                $result = ExamResult::where('exam_id',$exam->id)->where('student_id',$student->id)->first();
                $credits = $exam->subject?->credit_hours ?? 3;
                if (!$result) {
                    $subjects[] = ['subject'=>$exam->subject,'credits'=>$credits,'obtained'=>null,'total'=>$exam->total_marks,'pct'=>null,'grade'=>null,'status'=>'pending'];
                    continue;
                }
                $pct = $exam->total_marks > 0 ? ($result->marks_obtained / $exam->total_marks) * 100 : 0;
                $grade = $gradeService->getGrade($pct);
                $termTotalCredits += $credits;
                if ($grade['letter'] !== 'F') { $termEarnedCredits += $credits; $termEarnedPoints += $credits * $grade['points']; }
                $totalCredits += $credits;
                $earnedPoints += $credits * $grade['points'];
                $subjects[] = ['subject'=>$exam->subject,'credits'=>$credits,'obtained'=>$result->marks_obtained,'total'=>$exam->total_marks,'pct'=>round($pct,1),'grade'=>$grade,'status'=>$grade['letter']==='F'?'fail':'pass'];
            }

            $sgpa = $termTotalCredits > 0 ? round($termEarnedPoints/$termTotalCredits,2) : 0;
            $semesterReports[] = ['term'=>$term,'subjects'=>$subjects,'sgpa'=>$sgpa,'earned_credits'=>$termEarnedCredits,'total_credits'=>$termTotalCredits];
        }

        $cgpa = $totalCredits > 0 ? round($earnedPoints/$totalCredits,2) : 0;
        $transcript = AcademicTranscript::where('student_id',$student->id)->latest()->first();
        return view('academic.transcripts.show', compact('student','semesterReports','cgpa','totalCredits','transcript'));
    }

    public function generatePdf(Student $student) {
        $student->load(['user','program','batch']);
        $terms = Term::whereHas('batch', fn($q) => $q->where('id',$student->batch_id))
            ->orderBy('term_number')->get();
        $gradeService = new GradeService();
        $semesterReports = [];
        $totalCredits = 0;
        $earnedPoints = 0.0;

        foreach ($terms as $term) {
            $exams = Exam::where('program_id',$student->program_id)->where('term_id',$term->id)->with('subject')->get();
            $termTotalCredits = 0; $termEarnedCredits = 0; $termEarnedPoints = 0.0; $subjects = [];
            foreach ($exams as $exam) {
                $result = ExamResult::where('exam_id',$exam->id)->where('student_id',$student->id)->first();
                $credits = $exam->subject?->credit_hours ?? 3;
                if (!$result) { $subjects[] = ['subject'=>$exam->subject,'credits'=>$credits,'obtained'=>null,'total'=>$exam->total_marks,'pct'=>null,'grade'=>null,'status'=>'pending']; continue; }
                $pct = $exam->total_marks > 0 ? ($result->marks_obtained/$exam->total_marks)*100 : 0;
                $grade = $gradeService->getGrade($pct);
                $termTotalCredits += $credits;
                if ($grade['letter'] !== 'F') { $termEarnedCredits += $credits; $termEarnedPoints += $credits*$grade['points']; }
                $totalCredits += $credits; $earnedPoints += $credits*$grade['points'];
                $subjects[] = ['subject'=>$exam->subject,'credits'=>$credits,'obtained'=>$result->marks_obtained,'total'=>$exam->total_marks,'pct'=>round($pct,1),'grade'=>$grade,'status'=>$grade['letter']==='F'?'fail':'pass'];
            }
            $sgpa = $termTotalCredits > 0 ? round($termEarnedPoints/$termTotalCredits,2) : 0;
            $semesterReports[] = ['term'=>$term,'subjects'=>$subjects,'sgpa'=>$sgpa,'earned_credits'=>$termEarnedCredits,'total_credits'=>$termTotalCredits];
        }
        $cgpa = $totalCredits > 0 ? round($earnedPoints/$totalCredits,2) : 0;

        // Save transcript record
        AcademicTranscript::updateOrCreate(['student_id'=>$student->id], [
            'cgpa'=>$cgpa,'total_credits_earned'=>$totalCredits,
            'status'=>'issued','issued_by'=>auth()->id(),'issued_at'=>now(),
        ]);

        $pdf = Pdf::loadView('pdf.academic-transcript', compact('student','semesterReports','cgpa','totalCredits'))
            ->setPaper('a4','portrait');
        return $pdf->stream("transcript-{$student->enrollment_number}.pdf");
    }
}
