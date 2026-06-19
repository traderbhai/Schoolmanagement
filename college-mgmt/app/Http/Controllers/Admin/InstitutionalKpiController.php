<?php
namespace App\Http\Controllers\Admin;
use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\{Student, Teacher, Program, Batch, Exam, ExamResult, Attendance,
    Placement, PlacementDrive, FeeDemand, FeePayment, Lead, Applicant,
    OfferLetter, StudentGrievance, CurriculumChange, AlumniProfile, Internship, AuditLog};

class InstitutionalKpiController extends Controller {
    public function index() {
        abort_unless(request()->user() && AccessControl::canViewRegulatoryReports(request()->user()), 403);

        // Enrollment KPIs
        $totalStudents   = Student::where('status','active')->count();
        $totalGraduated  = Student::where('status','graduated')->count();
        $totalFaculty    = Teacher::where('status','active')->count();
        $totalPrograms   = Program::where('is_active',true)->count();
        $studentFacultyRatio = $totalFaculty > 0 ? round($totalStudents / $totalFaculty, 1) : 0;

        // Academic KPIs
        $totalExamsThisYear = Exam::whereYear('exam_date', now()->year)->count();
        $totalAtt = Attendance::count();
        $presentAtt = Attendance::where('status','present')->count();
        $attendancePct = $totalAtt > 0 ? round(($presentAtt/$totalAtt)*100,1) : 0;

        // Exam pass rate
        $allResults = ExamResult::with('exam')
            ->whereHas('exam', fn($q) => $q->whereNotNull('published_at'))
            ->get();
        $passCount = $allResults->filter(fn($r) => $r->exam && $r->marks_obtained >= ($r->exam->passing_marks ?? 40))->count();
        $passRate = $allResults->count() > 0 ? round(($passCount/$allResults->count())*100,1) : 0;

        // Financial KPIs
        $totalDemanded = FeeDemand::sum('final_amount');
        $totalCollected = FeeDemand::where('status','paid')->sum('final_amount');
        $collectionRate = $totalDemanded > 0 ? round(($totalCollected/$totalDemanded)*100,1) : 0;
        $outstanding = $totalDemanded - $totalCollected;

        // Admission KPIs
        $totalLeads = Lead::count();
        $totalApplicants = Applicant::count();
        $totalEnrolled = Student::count();
        $conversionRate = $totalLeads > 0 ? round(($totalEnrolled/$totalLeads)*100,1) : 0;

        // Placement KPIs
        $placed = Placement::distinct('student_id')->count('student_id');
        $placementRate = $totalStudents > 0 ? round(($placed/$totalStudents)*100,1) : 0;
        try { $avgSalary = Placement::where('salary','>',0)->avg('salary'); } catch (\Exception $e) { $avgSalary = null; }

        // Quality KPIs
        $openGrievances = 0;
        $pendingCurriculumChanges = 0;
        try { $openGrievances = StudentGrievance::whereIn('status',['open','under_review','escalated'])->count(); } catch (\Exception $e) {}
        try { $pendingCurriculumChanges = CurriculumChange::whereIn('status',['submitted','under_review'])->count(); } catch (\Exception $e) {}

        // Program health
        $programs = Program::where('is_active',true)->withCount(['students'=>fn($q)=>$q->where('status','active')])->get()
            ->map(function($prog) {
                $results = ExamResult::whereHas('exam', fn($q) => $q
                    ->whereNotNull('published_at')
                    ->where('program_id', $prog->id))->with('exam')->get();
                $total = $results->count();
                $passed = $total > 0 ? $results->filter(fn($r)=>$r->exam && $r->marks_obtained>=($r->exam->passing_marks??40))->count() : 0;
                $prog->pass_rate = $total > 0 ? round(($passed/$total)*100,1) : null;
                return $prog;
            });

        // Batch enrollment trend (last 5 batches)
        $batchTrend = Batch::with('program')->withCount(['students'=>fn($q)=>$q->where('status','active')])
            ->orderByDesc('id')->take(5)->get()->reverse()->values();

        return view('admin.institutional-kpi', compact(
            'totalStudents','totalGraduated','totalFaculty','totalPrograms','studentFacultyRatio',
            'totalExamsThisYear','attendancePct','passRate',
            'totalDemanded','totalCollected','collectionRate','outstanding',
            'totalLeads','totalApplicants','totalEnrolled','conversionRate',
            'placed','placementRate','avgSalary',
            'openGrievances','pendingCurriculumChanges',
            'programs','batchTrend'
        ));
    }
}
