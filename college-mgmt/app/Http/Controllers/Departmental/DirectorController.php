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

        $activeDrives = PlacementDrive::whereIn('status', ['upcoming', 'ongoing'])->count();

        // Overall attendance
        $attTotal = \App\Models\Attendance::count();
        $attPresent = \App\Models\Attendance::where('status','present')->count();
        $overallAttendance = $attTotal > 0 ? round(($attPresent/$attTotal)*100,1) : 0;

        // Pre-calculate all portal metrics in bulk (avoids N+1 inside buildPortalSummary loop)
        $preCalc = [
            'active_students'      => Student::where('status', 'active')->count(),
            'active_programs'      => $totalPrograms,
            'active_faculty'       => Teacher::where('status', 'active')->count(),
            'pending_dean'         => ApprovalWorkflow::where('approver_role', 'dean_academics')->where('status', 'pending')->count(),
            'pending_chair'        => ApprovalWorkflow::where('approver_role', 'program_chair')->where('status', 'pending')->count(),
            'pending_leaves'       => LeaveApplication::where('status', 'pending')->count(),
            'overdue_approvals'    => ApprovalWorkflow::where('status', 'pending')->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'exams_this_year'      => Exam::whereYear('exam_date', now()->year)->count(),
            'placed_this_year'     => $placedThisYear,
            'active_drives'        => $activeDrives,
            'fee_this_year'        => Schema::hasTable('fee_payments')
                ? 'Rs. ' . number_format(\App\Models\FeePayment::where('status', 'paid')->whereYear('payment_date', now()->year)->sum('amount_paid'), 0)
                : '-',
        ];

        // Build portal summaries from org hierarchy
        $childLines = OrgReportingLine::getChildRoles('director');
        $portalSummaries = [];
        foreach ($childLines as $line) {
            try {
                $summary = $this->buildPortalSummary($line->child_role, $line->can_view_full, $preCalc);
                if ($summary) $portalSummaries[] = $summary;
            } catch (\Throwable $e) {}
        }

        $lowEnrollmentCount = Program::where('is_active', true)
            ->withCount('students')
            ->get()
            ->filter(fn($program) => $program->students_count < 5)
            ->count();

        $directorPriority = $this->directorPriority(
            $preCalc['overdue_approvals'],
            ($preCalc['pending_dean'] ?? 0) + ($preCalc['pending_chair'] ?? 0),
            $overallAttendance,
            $lowEnrollmentCount,
            $activeDrives,
            $childLines->count()
        );

        return view('departmental.director.dashboard', compact(
            'totalStudents', 'totalPrograms', 'placedThisYear',
            'totalFaculty', 'programs', 'activeDrives', 'overallAttendance', 'portalSummaries', 'directorPriority'
        ));
    }

    private function directorPriority(int $overdueApprovals, int $pendingApprovals, float $overallAttendance, int $lowEnrollmentCount, int $activeDrives, int $configuredReportingLines): array
    {
        if ($overdueApprovals > 0) {
            return [
                'level' => 'danger',
                'title' => "Escalate {$overdueApprovals} overdue approval" . ($overdueApprovals === 1 ? '' : 's'),
                'body' => 'Overdue academic approvals create admission and operational bottlenecks across dean and program teams.',
                'route' => route('dean.approvals'),
                'action' => 'Review Approvals',
            ];
        }

        if ($pendingApprovals > 0) {
            return [
                'level' => 'warning',
                'title' => "Monitor {$pendingApprovals} pending academic approval" . ($pendingApprovals === 1 ? '' : 's'),
                'body' => 'Pending dean and program-chair decisions need close follow-up to keep admissions and offers moving.',
                'route' => route('dean.approvals'),
                'action' => 'Open Approval Queue',
            ];
        }

        if ($overallAttendance > 0 && $overallAttendance < 75) {
            return [
                'level' => 'danger',
                'title' => 'Institute attendance is below threshold',
                'body' => "Overall attendance is {$overallAttendance}%. Review academic interventions with Dean and HODs.",
                'route' => route('dean.attendance'),
                'action' => 'Review Attendance',
            ];
        }

        if ($lowEnrollmentCount > 0) {
            return [
                'level' => 'warning',
                'title' => "Review {$lowEnrollmentCount} low-enrollment program" . ($lowEnrollmentCount === 1 ? '' : 's'),
                'body' => 'Low enrollment affects faculty planning, admissions targets, and financial sustainability.',
                'route' => route('director.reports'),
                'action' => 'Open Reports',
            ];
        }

        if ($activeDrives === 0) {
            return [
                'level' => 'info',
                'title' => 'No active placement drives',
                'body' => 'Coordinate with CMC to keep placement opportunities visible for eligible students.',
                'route' => route('cmc.dashboard'),
                'action' => 'Open CMC',
            ];
        }

        if ($configuredReportingLines === 0) {
            return [
                'level' => 'warning',
                'title' => 'Configure executive reporting lines',
                'body' => 'Org hierarchy reporting is not configured, so portal summaries cannot reflect operating ownership.',
                'route' => route('admin.org-hierarchy.index'),
                'action' => 'Configure Hierarchy',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No urgent executive action today',
            'body' => 'Use this time to review institutional KPIs, academic health, placements, finance, and reporting lines.',
            'route' => route('director.reports'),
            'action' => 'Open Reports',
        ];
    }

    private function buildPortalSummary(string $role, bool $canFull, array $pre = []): ?array
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
                ['label'=>'Active Students',  'value'=> $pre['active_students'] ?? 0],
                ['label'=>'Programs',         'value'=> $pre['active_programs'] ?? 0],
                ['label'=>'Pending Approvals','value'=> $pre['pending_dean'] ?? 0],
            ],
            'hod'              => [
                ['label'=>'Active Faculty', 'value'=> $pre['active_faculty'] ?? 0],
                ['label'=>'Pending Leaves', 'value'=> $pre['pending_leaves'] ?? 0],
            ],
            'program_chair'    => [
                ['label'=>'Students',         'value'=> $pre['active_students'] ?? 0],
                ['label'=>'Pending Approvals','value'=> $pre['pending_chair'] ?? 0],
            ],
            'exam_cell'        => [
                ['label'=>'Exams This Year', 'value'=> $pre['exams_this_year'] ?? 0],
            ],
            'accounts_officer' => [
                ['label'=>'Fee Collections This Year', 'value'=> $pre['fee_this_year'] ?? '-'],
            ],
            'cmc'              => [
                ['label'=>'Placed This Year','value'=> $pre['placed_this_year'] ?? 0],
                ['label'=>'Active Drives',   'value'=> $pre['active_drives'] ?? 0],
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
        $activeDrives   = PlacementDrive::whereIn('status', ['upcoming', 'ongoing'])->count();
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
