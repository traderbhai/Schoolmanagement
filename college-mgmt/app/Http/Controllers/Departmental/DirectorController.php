<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Student, Program, PlacementDrive, Placement, User, Teacher,
    Exam, ApprovalWorkflow, LeaveApplication, OrgReportingLine};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DirectorController extends Controller
{
    public function dashboard()
    {
        $totalStudents = Student::count();
        $totalPrograms = Program::where('is_active', true)->count();
        $placedThisYear = Placement::where('application_status', 'selected')
            ->whereYear('created_at', now()->year)
            ->count();

        $totalFaculty = User::whereHas('roles', fn($q) => $q->where('name', 'teacher'))->count();

        $programs = Program::where('is_active', true)
            ->withCount('students')
            ->orderBy('students_count', 'desc')
            ->take(6)
            ->get();

        $activeDrives = PlacementDrive::where(function ($q) {
            $q->where('status', 'open')->orWhere('status', 'active');
        })->count();

        // Overall attendance
        $attTotal = \App\Models\Attendance::count();
        $attPresent = \App\Models\Attendance::where('status','present')->count();
        $overallAttendance = $attTotal > 0 ? round(($attPresent/$attTotal)*100,1) : 0;

        // Build portal summaries from org hierarchy
        $childLines = OrgReportingLine::getChildRoles('director');
        $portalSummaries = [];
        foreach ($childLines as $line) {
            try {
                $summary = $this->buildPortalSummary($line->child_role, $line->can_view_full);
                if ($summary) $portalSummaries[] = $summary;
            } catch (\Throwable $e) {}
        }

        return view('departmental.director.dashboard', compact(
            'totalStudents', 'totalPrograms', 'placedThisYear',
            'totalFaculty', 'programs', 'activeDrives', 'overallAttendance', 'portalSummaries'
        ));
    }

    private function buildPortalSummary(string $role, bool $canFull): ?array
    {
        $config = [
            'dean_academics'   => ['label'=>'Dean Academics','icon'=>'bi-mortarboard-fill','color'=>'primary','route'=>'dean.dashboard'],
            'hod'              => ['label'=>'Head of Dept','icon'=>'bi-building','color'=>'success','route'=>'hod.dashboard'],
            'program_chair'    => ['label'=>'Program Chair / PMC','icon'=>'bi-diagram-3-fill','color'=>'info','route'=>'chair.dashboard'],
            'exam_cell'        => ['label'=>'Exam Cell','icon'=>'bi-file-earmark-check','color'=>'warning','route'=>'exam-cell.dashboard'],
            'accounts_officer' => ['label'=>'Accounts','icon'=>'bi-cash-stack','color'=>'success','route'=>'accounts.dashboard'],
            'cmc'              => ['label'=>'CMC / Placement','icon'=>'bi-briefcase-fill','color'=>'purple','route'=>'cmc.dashboard'],
            'admin'            => ['label'=>'Admin','icon'=>'bi-shield-fill','color'=>'dark','route'=>'admin.dashboard'],
        ];
        if (!isset($config[$role])) return null;

        $base = $config[$role];
        $base['can_view_full'] = $canFull;
        $base['role'] = $role;

        $base['metrics'] = match($role) {
            'dean_academics'   => [
                ['label'=>'Active Students', 'value'=> Student::where('status','active')->count()],
                ['label'=>'Programs',        'value'=> Program::where('is_active',true)->count()],
                ['label'=>'Pending Approvals','value'=> ApprovalWorkflow::where('approver_role','dean_academics')->where('status','pending')->count()],
            ],
            'hod'              => [
                ['label'=>'Active Faculty',  'value'=> Teacher::where('status','active')->count()],
                ['label'=>'Pending Leaves',  'value'=> LeaveApplication::where('status','pending')->count()],
            ],
            'program_chair'    => [
                ['label'=>'Students', 'value'=> Student::where('status','active')->count()],
                ['label'=>'Pending Approvals','value'=> ApprovalWorkflow::where('approver_role','program_chair')->where('status','pending')->count()],
            ],
            'exam_cell'        => [
                ['label'=>'Exams This Year', 'value'=> Exam::whereYear('exam_date',now()->year)->count()],
            ],
            'accounts_officer' => [
                ['label'=>'Fee Collections This Year', 'value'=> Schema::hasTable('fee_payments')
                    ? '₹'.number_format(\App\Models\FeePayment::where('status','paid')->whereYear('payment_date',now()->year)->sum('amount_paid'),0)
                    : '—'],
            ],
            'cmc'              => [
                ['label'=>'Placed This Year','value'=> Placement::where('application_status','selected')->whereYear('created_at',now()->year)->count()],
                ['label'=>'Active Drives',   'value'=> PlacementDrive::whereIn('status',['open','active'])->count()],
            ],
            default => [],
        };

        return $base;
    }

    public function programs()
    {
        $programs = Program::where('is_active', true)
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return view('departmental.director.programs', compact('programs'));
    }

    public function reports()
    {
        $currentYear = now()->year;
        $totalStudents = Student::count();

        // Placement metrics
        $placedThisYear = Placement::where('application_status', 'selected')
            ->whereYear('created_at', $currentYear)->count();
        $placementRate = $totalStudents > 0
            ? round(($placedThisYear / $totalStudents) * 100, 1)
            : 0;

        // Enrollment by program (top programs)
        $enrollmentByProgram = Program::where('is_active', true)
            ->withCount('students')
            ->orderBy('students_count', 'desc')
            ->get(['id', 'name', 'code']);

        // Fee collection this year
        $feeCollectedThisYear = \App\Models\FeePayment::where('status', 'paid')
            ->whereYear('payment_date', $currentYear)
            ->sum('amount_paid');

        $feeCollectedLastYear = \App\Models\FeePayment::where('status', 'paid')
            ->whereYear('payment_date', $currentYear - 1)
            ->sum('amount_paid');

        // Attendance overview
        $totalAttendance = \App\Models\Attendance::whereYear('date', $currentYear)->count();
        $presentCount    = \App\Models\Attendance::whereYear('date', $currentYear)
            ->where('status', 'present')->count();
        $overallAttendancePct = $totalAttendance > 0
            ? round(($presentCount / $totalAttendance) * 100, 1)
            : 0;

        // Active drives & total placed all time
        $activeDrives   = PlacementDrive::whereIn('status', ['open', 'active'])->count();
        $totalPlaced    = Placement::where('application_status', 'selected')->count();

        // Programs with low enrollment (< 5 students)
        $lowEnrollmentPrograms = $enrollmentByProgram->filter(fn($p) => $p->students_count < 5);

        return view('departmental.director.reports', compact(
            'totalStudents', 'placementRate', 'placedThisYear',
            'enrollmentByProgram', 'feeCollectedThisYear', 'feeCollectedLastYear',
            'overallAttendancePct', 'activeDrives', 'totalPlaced',
            'lowEnrollmentPrograms', 'currentYear'
        ));
    }
}
