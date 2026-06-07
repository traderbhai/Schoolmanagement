<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Program, Student, Teacher, Exam, ExamResult, Attendance, Subject, Batch, Term};
use Barryvdh\DomPDF\Facade\Pdf;

class AicteReportController extends Controller {
    public function index() {
        $programs = Program::where('is_active',true)->with(['department','batches'])->get()
            ->map(fn($p) => $this->buildProgramStats($p));
        $totalFaculty = Teacher::where('status','active')->count();
        $totalStudents = Student::where('status','active')->count();
        $reportYear = now()->year;
        return view('admin.aicte-report', compact('programs','totalFaculty','totalStudents','reportYear'));
    }

    private function buildProgramStats($program): object {
        $program->active_students = Student::where('program_id',$program->id)->where('status','active')->count();
        $program->subject_count = Subject::where('program_id',$program->id)->count();
        $program->faculty_count = Teacher::where('department_id',$program->department_id)->where('status','active')->count();
        $program->exam_count = Exam::where('program_id',$program->id)->whereYear('exam_date',now()->year)->count();

        $results = ExamResult::whereHas('exam',fn($q)=>$q->where('program_id',$program->id))->with('exam')->get();
        $total = $results->count();
        $passed = $total > 0 ? $results->filter(fn($r)=>$r->exam && $r->marks_obtained>=($r->exam->passing_marks??40))->count() : 0;
        $program->pass_rate = $total > 0 ? round(($passed/$total)*100,1) : null;

        $att = Attendance::whereHas('student',fn($q)=>$q->where('program_id',$program->id))->count();
        $present = Attendance::whereHas('student',fn($q)=>$q->where('program_id',$program->id))->where('status','present')->count();
        $program->attendance_pct = $att > 0 ? round(($present/$att)*100,1) : null;

        return $program;
    }

    public function exportPdf() {
        $programs = Program::where('is_active',true)->with(['department','batches'])->get()
            ->map(fn($p) => $this->buildProgramStats($p));
        $totalFaculty = Teacher::where('status','active')->count();
        $totalStudents = Student::where('status','active')->count();
        $reportYear = now()->year;

        $pdf = Pdf::loadView('pdf.aicte-report', compact('programs','totalFaculty','totalStudents','reportYear'))
            ->setPaper('a4','landscape');
        return $pdf->stream("aicte-report-{$reportYear}.pdf");
    }
}
