<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EduManage — Portal')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    @stack('styles')
</head>
<body>

@php
    $user = auth()->user();
    if ($user->hasRole('admin')) {
        $brandName  = 'EduManage';
        $brandSub   = 'Admin Portal';
        $brandRoute = 'admin.dashboard';
    } elseif ($user->hasRole('dean_academics')) {
        $brandName  = 'Dean Academics';
        $brandSub   = 'Academic Office';
        $brandRoute = 'dean.dashboard';
    } elseif ($user->hasRole('hod')) {
        $brandName  = 'HOD Portal';
        $brandSub   = 'Head of Department';
        $brandRoute = 'hod.dashboard';
    } elseif ($user->hasRole('program_chair')) {
        $brandName  = 'PMC Portal';
        $brandSub   = 'Program Management';
        $brandRoute = 'chair.dashboard';
    } elseif ($user->hasRole('exam_cell')) {
        $brandName  = 'Exam Cell';
        $brandSub   = 'Examinations Office';
        $brandRoute = 'exam-cell.dashboard';
    } elseif ($user->hasRole('accounts_officer')) {
        $brandName  = 'Accounts';
        $brandSub   = 'Finance Office';
        $brandRoute = 'accounts.dashboard';
    } elseif ($user->hasRole('cmc')) {
        $brandName  = 'CMC Portal';
        $brandSub   = 'Placement & Careers';
        $brandRoute = 'cmc.dashboard';
    } elseif ($user->hasRole('director')) {
        $brandName  = "Director's Office";
        $brandSub   = 'Institute Management';
        $brandRoute = 'director.dashboard';
    } elseif ($user->hasAnyRole(['admission_head', 'admission_officer'])) {
        $brandName  = 'Admissions';
        $brandSub   = 'CRM & Enrollment';
        $brandRoute = 'admission.dashboard';
    } else {
        $brandName  = 'EduManage';
        $brandSub   = 'Portal';
        $brandRoute = 'admin.dashboard';
    }

    $firstProgram = \App\Models\Program::where('is_active', true)->first();
@endphp

{{-- ===== DESKTOP SIDEBAR ===== --}}
<div class="sidebar sidebar-desktop">
    <a class="sidebar-brand" href="{{ route($brandRoute) }}">
        <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
        <span>
            <div class="brand-text">{{ $brandName }}</div>
            <div class="brand-sub">{{ $brandSub }}</div>
        </span>
    </a>

    <div class="mt-2 pb-4 flex-grow-1">

        {{-- ===================== ADMIN ===================== --}}
        @hasrole('admin')

        <div class="section-label">Main</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-link @if(request()->routeIs('admin.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Academic Setup</div>
        <a href="{{ route('admin.academic-years.index') }}" class="nav-link @if(request()->routeIs('admin.academic-years.*')) active @endif">
            <i class="bi bi-calendar3"></i> Academic Years
        </a>
        <a href="{{ route('admin.semesters.index') }}" class="nav-link @if(request()->routeIs('admin.semesters.*')) active @endif">
            <i class="bi bi-calendar-range"></i> Semesters
        </a>
        <a href="{{ route('admin.departments.index') }}" class="nav-link @if(request()->routeIs('admin.departments.*')) active @endif">
            <i class="bi bi-building"></i> Departments
        </a>
        <a href="{{ route('admin.subjects.index') }}" class="nav-link @if(request()->routeIs('admin.subjects.*')) active @endif">
            <i class="bi bi-book"></i> Subjects
        </a>
        <a href="{{ route('admin.classrooms.index') }}" class="nav-link @if(request()->routeIs('admin.classrooms.*')) active @endif">
            <i class="bi bi-door-open"></i> Classrooms
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Academic Structure</div>
        <a href="{{ route('admin.programs.index') }}" class="nav-link @if(request()->routeIs('admin.programs.*') || request()->routeIs('admin.admission-config.*')) active @endif">
            <i class="bi bi-mortarboard"></i> Programs
        </a>
        <a href="{{ route('admin.batches.index') }}" class="nav-link @if(request()->routeIs('admin.batches.*')) active @endif">
            <i class="bi bi-collection"></i> Batches
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Timetable</div>
        <a href="{{ route('admin.timetable-slots.index') }}" class="nav-link @if(request()->routeIs('admin.timetable-slots.*')) active @endif">
            <i class="bi bi-clock"></i> Time Slots
        </a>
        <a href="{{ route('admin.timetable.index') }}" class="nav-link @if(request()->routeIs('admin.timetable.index') || request()->routeIs('admin.timetable.create') || request()->routeIs('admin.timetable.edit')) active @endif">
            <i class="bi bi-grid-3x3-gap"></i> Weekly Timetable
        </a>
        <a href="{{ route('admin.timetable.teacher-view') }}" class="nav-link @if(request()->routeIs('admin.timetable.teacher-view')) active @endif">
            <i class="bi bi-person-lines-fill"></i> Teacher View
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">People</div>
        <a href="{{ route('admin.teachers.index') }}" class="nav-link @if(request()->routeIs('admin.teachers.*')) active @endif">
            <i class="bi bi-person-badge"></i> Teachers
        </a>
        <a href="{{ route('admin.students.index') }}" class="nav-link @if(request()->routeIs('admin.students.*')) active @endif">
            <i class="bi bi-people"></i> Students
        </a>
        <a href="{{ route('admin.applicants.index') }}" class="nav-link @if(request()->routeIs('admin.applicants.*')) active @endif">
            <i class="bi bi-person-lines-fill"></i> Applications
        </a>
        <a href="{{ route('admin.admissions.index') }}" class="nav-link @if(request()->routeIs('admin.admissions.*')) active @endif">
            <i class="bi bi-person-plus-fill"></i> Admissions
        </a>
        <a href="{{ route('admin.parents.index') }}" class="nav-link @if(request()->routeIs('admin.parents.*')) active @endif">
            <i class="bi bi-people-fill"></i> Parents
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Admission CRM</div>
        <a href="{{ route('admission.dashboard') }}" class="nav-link @if(request()->routeIs('admission.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> CRM Dashboard
        </a>
        <a href="{{ route('admission.applicants.index') }}" class="nav-link @if(request()->routeIs('admission.applicants.*')) active @endif">
            <i class="bi bi-person-lines-fill"></i> Applicants CRM
        </a>
        <a href="{{ route('admission.sessions.index') }}" class="nav-link @if(request()->routeIs('admission.sessions.*')) active @endif">
            <i class="bi bi-calendar-event"></i> Sessions
        </a>
        <a href="{{ route('admission.documents.queue') }}" class="nav-link @if(request()->routeIs('admission.documents.*')) active @endif">
            <i class="bi bi-folder-check"></i> Document Queue
            @php $docsPending = \App\Models\ApplicantDocument::where('status','pending')->count(); @endphp
            @if($docsPending > 0)<span class="badge bg-warning text-dark ms-1">{{ $docsPending }}</span>@endif
        </a>
        <a href="{{ route('admission.payments.queue') }}" class="nav-link @if(request()->routeIs('admission.payments.queue')) active @endif">
            <i class="bi bi-cash-coin"></i> Payment Queue
            @php $paymentsPending = \App\Models\AdmissionPayment::where('status','pending')->count(); @endphp
            @if($paymentsPending > 0)<span class="badge bg-info text-dark ms-1">{{ $paymentsPending }}</span>@endif
        </a>
        @if($firstProgram)
        <a href="{{ route('admission.merit-list.index', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.merit-list.*')) active @endif">
            <i class="bi bi-list-ol"></i> Merit List
        </a>
        <a href="{{ route('admission.offer-letters.index', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.offer-letters.*')) active @endif">
            <i class="bi bi-envelope-open"></i> Offer Letters
        </a>
        @endif
        <a href="{{ route('admission.enrollment.index') }}" class="nav-link @if(request()->routeIs('admission.enrollment.*')) active @endif">
            <i class="bi bi-person-check-fill"></i> Enrollments
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Leads & Pipeline</div>
        <a href="{{ route('admission.leads.index') }}" class="nav-link @if(request()->routeIs('admission.leads.index') || request()->routeIs('admission.leads.show')) active @endif">
            <i class="bi bi-funnel"></i> All Leads
            @php $newLeads = \App\Models\Lead::where('status','new')->count(); @endphp
            @if($newLeads > 0)<span class="badge bg-info text-dark ms-1">{{ $newLeads }}</span>@endif
        </a>
        <a href="{{ route('admission.leads.import') }}" class="nav-link @if(request()->routeIs('admission.leads.import')) active @endif">
            <i class="bi bi-upload"></i> Import Leads
        </a>
        <a href="{{ route('admission.leads.follow-ups.calendar') }}" class="nav-link @if(request()->routeIs('admission.leads.follow-ups.*')) active @endif">
            <i class="bi bi-calendar3"></i> Follow-up Calendar
        </a>
        <a href="{{ route('admission.leads.analytics') }}" class="nav-link @if(request()->routeIs('admission.leads.analytics')) active @endif">
            <i class="bi bi-graph-up-arrow"></i> Lead Analytics
        </a>
        <a href="{{ route('admission.reports.index') }}" class="nav-link @if(request()->routeIs('admission.reports.*')) active @endif">
            <i class="bi bi-bar-chart-line"></i> Admission Reports
        </a>
        <a href="{{ route('admission.bulk-communication.index') }}" class="nav-link @if(request()->routeIs('admission.bulk-communication.*')) active @endif">
            <i class="bi bi-megaphone"></i> Bulk Communication
        </a>
        <a href="{{ route('admission.refunds.index') }}" class="nav-link @if(request()->routeIs('admission.refunds.*')) active @endif">
            <i class="bi bi-arrow-counterclockwise"></i> Refunds
        </a>

        @if($firstProgram)
        <div class="sidebar-divider"></div>
        <div class="section-label">Program Tools</div>
        <a href="{{ route('admission.seat-matrices.index', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.seat-matrices.*')) active @endif">
            <i class="bi bi-grid-3x3"></i> Seat Matrix
        </a>
        <a href="{{ route('admission.selection-process.steps', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.selection-process.*')) active @endif">
            <i class="bi bi-diagram-3"></i> Selection Process
        </a>
        <a href="{{ route('admission.fee-installments.index', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.fee-installments.*')) active @endif">
            <i class="bi bi-credit-card"></i> Fee Installments
        </a>
        @endif

        <div class="sidebar-divider"></div>
        <div class="section-label">Scholarships</div>
        <a href="{{ route('admission.scholarship-schemes.index') }}" class="nav-link {{ request()->routeIs('admission.scholarship-schemes.*') ? 'active' : '' }}">
            <i class="bi bi-award me-2"></i>Scholarship Schemes
        </a>
        <a href="{{ route('admission.scholarship-disbursements.index') }}" class="nav-link {{ request()->routeIs('admission.scholarship-disbursements.*') ? 'active' : '' }}">
            <i class="bi bi-send me-2"></i>Disbursements
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Academics</div>
        <a href="{{ route('academic.curriculum-changes.index') }}" class="nav-link @if(request()->routeIs('academic.curriculum-changes.*')) active @endif">
            <i class="bi bi-journal-text"></i> Curriculum Changes
        </a>
        <a href="{{ route('academic.obe.co.index') }}" class="nav-link @if(request()->routeIs('academic.obe.*')) active @endif">
            <i class="bi bi-diagram-3 me-2"></i>OBE Framework
        </a>
        <a href="{{ route('hod.faculty.roster') }}" class="nav-link @if(request()->routeIs('hod.faculty.*')) active @endif">
            <i class="bi bi-people"></i> Faculty Roster
        </a>
        <a href="{{ route('hod.leaves') }}" class="nav-link @if(request()->routeIs('hod.leaves*')) active @endif">
            <i class="bi bi-calendar-x"></i> Leave Approvals
        </a>
        <a href="{{ route('admin.leaves.index') }}" class="nav-link @if(request()->routeIs('admin.leaves.*')) active @endif">
            <i class="bi bi-calendar-x"></i> Leave Mgmt
        </a>
        <a href="{{ route('admin.faculty.workload') }}" class="nav-link @if(request()->routeIs('admin.faculty.*')) active @endif">
            <i class="bi bi-bar-chart"></i> Faculty Workload
        </a>
        <a href="{{ route('admin.attendance.index') }}" class="nav-link @if(request()->routeIs('admin.attendance.*')) active @endif">
            <i class="bi bi-check2-square"></i> Attendance
        </a>
        <a href="{{ route('admin.exams.index') }}" class="nav-link @if(request()->routeIs('admin.exams.*')) active @endif">
            <i class="bi bi-file-earmark-text"></i> Exams &amp; Results
        </a>
        <a href="{{ route('admin.enrollments.index') }}" class="nav-link @if(request()->routeIs('admin.enrollments.*')) active @endif">
            <i class="bi bi-person-check"></i> Enrollments
        </a>
        <a href="{{ route('admin.results.index') }}" class="nav-link @if(request()->routeIs('admin.results.*')) active @endif">
            <i class="bi bi-award"></i> Grade Reports
        </a>
        <a href="{{ route('academic.transcripts.index') }}" class="nav-link @if(request()->routeIs('academic.transcripts.*')) active @endif">
            <i class="bi bi-file-earmark-text"></i> Transcripts
        </a>
        <a href="{{ route('exam-cell.exams.create') }}" class="nav-link @if(request()->routeIs('exam-cell.exams.create')) active @endif">
            <i class="bi bi-plus-circle"></i> Schedule Exam
        </a>
        <a href="{{ route('exam-cell.hall-tickets') }}" class="nav-link @if(request()->routeIs('exam-cell.hall-tickets*')) active @endif">
            <i class="bi bi-ticket-perforated"></i> Hall Tickets
        </a>
        <a href="{{ route('exam-cell.marks-appeals') }}" class="nav-link @if(request()->routeIs('exam-cell.marks-appeals*')) active @endif">
            <i class="bi bi-envelope-exclamation"></i> Marks Appeals
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Finance</div>
        <a href="{{ route('admin.fees.index') }}" class="nav-link @if(request()->routeIs('admin.fees.index') || request()->routeIs('admin.fees.show') || request()->routeIs('admin.fees.create') || request()->routeIs('admin.fees.edit') || request()->routeIs('admin.fees.collect') || request()->routeIs('admin.fees.receipt')) active @endif">
            <i class="bi bi-cash-coin"></i> Fees
        </a>
        <a href="{{ route('admin.fees.report') }}" class="nav-link @if(request()->routeIs('admin.fees.report')) active @endif">
            <i class="bi bi-graph-up"></i> Fee Report
        </a>
        <a href="{{ route('accounts.dashboard') }}" class="nav-link @if(request()->routeIs('accounts.dashboard')) active @endif">
            <i class="bi bi-cash-stack"></i> Accounts Dashboard
        </a>
        <a href="{{ route('accounts.reconciliation') }}" class="nav-link {{ request()->routeIs('accounts.reconciliation') ? 'active' : '' }}">
            <i class="bi bi-arrow-left-right"></i> Reconciliation
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Placement</div>
        <a href="{{ route('admin.companies.index') }}" class="nav-link @if(request()->routeIs('admin.companies.*')) active @endif">
            <i class="bi bi-building"></i> Companies
        </a>
        <a href="{{ route('admin.placement-drives.index') }}" class="nav-link @if(request()->routeIs('admin.placement-drives.*')) active @endif">
            <i class="bi bi-briefcase"></i> Drives
        </a>
        <a href="{{ route('cmc.drives') }}" class="nav-link @if(request()->routeIs('cmc.drives*')) active @endif">
            <i class="bi bi-briefcase"></i> Placement Drives
        </a>
        <a href="{{ route('cmc.placement-stats') }}" class="nav-link @if(request()->routeIs('cmc.placement-stats')) active @endif">
            <i class="bi bi-bar-chart-line"></i> Placement Stats
        </a>
        <a href="{{ route('cmc.internships.index') }}" class="nav-link @if(request()->routeIs('cmc.internships.*')) active @endif">
            <i class="bi bi-laptop"></i> Internships
        </a>
        <a href="{{ route('cmc.alumni.index') }}" class="nav-link @if(request()->routeIs('cmc.alumni.*')) active @endif">
            <i class="bi bi-people-fill"></i> Alumni Database
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Communication</div>
        <a href="{{ route('admin.notices.index') }}" class="nav-link @if(request()->routeIs('admin.notices.*')) active @endif">
            <i class="bi bi-megaphone"></i> Notices
        </a>
        <a href="{{ route('admin.bulk-mail.index') }}" class="nav-link @if(request()->routeIs('admin.bulk-mail.*')) active @endif">
            <i class="bi bi-envelope-paper"></i> Bulk Mail
        </a>
        <a href="{{ route('admin.email-logs.index') }}" class="nav-link @if(request()->routeIs('admin.email-logs.*')) active @endif">
            <i class="bi bi-journal-text"></i> Email Logs
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Approvals</div>
        <a href="{{ route('approvals.inbox') }}" class="nav-link @if(request()->routeIs('approvals.*')) active @endif">
            <i class="bi bi-check2-circle"></i> My Approvals
            @php $pendingCount = \App\Models\ApprovalWorkflow::whereIn('approver_role', auth()->user()->getRoleNames()->toArray())->where('status','pending')->count(); @endphp
            @if($pendingCount > 0)<span class="badge bg-warning text-dark ms-1">{{ $pendingCount }}</span>@endif
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Access Control</div>
        <a href="{{ route('admin.roles.hierarchy') }}" class="nav-link @if(request()->routeIs('admin.roles.hierarchy')) active @endif">
            <i class="bi bi-diagram-3"></i> Role Hierarchy
        </a>
        <a href="{{ route('admin.roles.permissions.index') }}" class="nav-link @if(request()->routeIs('admin.roles.permissions.*')) active @endif">
            <i class="bi bi-key"></i> Permissions
        </a>
        <a href="{{ route('admin.roles.feature-access.index') }}" class="nav-link @if(request()->routeIs('admin.roles.feature-access.*')) active @endif">
            <i class="bi bi-grid-3x3"></i> Feature Matrix
        </a>
        <a href="{{ route('admin.users.roles.index') }}" class="nav-link @if(request()->routeIs('admin.users.roles.*')) active @endif">
            <i class="bi bi-person-badge"></i> Role Assignments
        </a>
        <a href="{{ route('admin.role-assignments.index') }}" class="nav-link @if(request()->routeIs('admin.role-assignments.*')) active @endif">
            <i class="bi bi-shield-lock"></i> Legacy Assignments
        </a>
        <a href="{{ route('admin.org-hierarchy.index') }}" class="nav-link @if(request()->routeIs('admin.org-hierarchy.*')) active @endif">
            <i class="bi bi-diagram-3-fill"></i> Org Hierarchy
        </a>
        <a href="{{ route('admin.audit.index') }}" class="nav-link @if(request()->routeIs('admin.audit.*')) active @endif">
            <i class="bi bi-journal-text"></i> Audit Log
        </a>
        <a href="{{ route('admin.grievances.index') }}" class="nav-link @if(request()->routeIs('admin.grievances.*')) active @endif">
            <i class="bi bi-shield-exclamation"></i> Grievances
            @php $openGrievances = \App\Models\StudentGrievance::whereIn('status',['open'])->count(); @endphp
            @if($openGrievances > 0)<span class="badge bg-warning text-dark ms-1">{{ $openGrievances }}</span>@endif
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">System</div>
        <a href="{{ route('admin.analytics') }}" class="nav-link @if(request()->routeIs('admin.analytics')) active @endif">
            <i class="bi bi-graph-up-arrow"></i> Analytics
        </a>
        <a href="{{ route('admin.institutional-kpi') }}" class="nav-link @if(request()->routeIs('admin.institutional-kpi')) active @endif">
            <i class="bi bi-speedometer2"></i> Institutional KPI
        </a>
        <a href="{{ route('admin.aicte-report') }}" class="nav-link @if(request()->routeIs('admin.aicte-report*')) active @endif">
            <i class="bi bi-file-earmark-bar-graph"></i> AICTE Report
        </a>
        <a href="{{ route('admin.settings') }}" class="nav-link @if(request()->routeIs('admin.settings*')) active @endif">
            <i class="bi bi-gear"></i> Settings
        </a>
        <a href="{{ route('admin.activity-log') }}" class="nav-link @if(request()->routeIs('admin.activity-log')) active @endif">
            <i class="bi bi-clock-history"></i> Activity Log
        </a>

        @endhasrole

        {{-- ===================== DEAN ACADEMICS ===================== --}}
        @hasrole('dean_academics')

        <div class="section-label">Main</div>
        <a href="{{ route('dean.dashboard') }}" class="nav-link @if(request()->routeIs('dean.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Academic Oversight</div>
        <a href="{{ route('dean.academics') }}" class="nav-link @if(request()->routeIs('dean.academics')) active @endif">
            <i class="bi bi-mortarboard-fill"></i> Academics Overview
        </a>
        <a href="{{ route('dean.programs') }}" class="nav-link @if(request()->routeIs('dean.programs')) active @endif">
            <i class="bi bi-mortarboard"></i> Programs
        </a>
        <a href="{{ route('dean.students') }}" class="nav-link @if(request()->routeIs('dean.students')) active @endif">
            <i class="bi bi-people"></i> Students
        </a>
        <a href="{{ route('dean.attendance') }}" class="nav-link @if(request()->routeIs('dean.attendance')) active @endif">
            <i class="bi bi-check2-square"></i> Attendance
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Curriculum</div>
        <a href="{{ route('academic.curriculum-changes.index') }}" class="nav-link @if(request()->routeIs('academic.curriculum-changes.*')) active @endif">
            <i class="bi bi-journal-text"></i> Curriculum Changes
        </a>
        <a href="{{ route('academic.obe.co.index') }}" class="nav-link @if(request()->routeIs('academic.obe.*')) active @endif">
            <i class="bi bi-diagram-3 me-2"></i>OBE Framework
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Exams</div>
        <a href="{{ route('exam-cell.exams') }}" class="nav-link @if(request()->routeIs('exam-cell.exams') && !request()->routeIs('exam-cell.exams.create')) active @endif">
            <i class="bi bi-file-earmark-text"></i> Exams
        </a>
        <a href="{{ route('academic.transcripts.index') }}" class="nav-link @if(request()->routeIs('academic.transcripts.*')) active @endif">
            <i class="bi bi-file-earmark-text"></i> Transcripts
        </a>
        <a href="{{ route('exam-cell.hall-tickets') }}" class="nav-link @if(request()->routeIs('exam-cell.hall-tickets*')) active @endif">
            <i class="bi bi-ticket-perforated"></i> Hall Tickets
        </a>
        <a href="{{ route('exam-cell.marks-appeals') }}" class="nav-link @if(request()->routeIs('exam-cell.marks-appeals*')) active @endif">
            <i class="bi bi-envelope-exclamation"></i> Marks Appeals
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Approvals</div>
        <a href="{{ route('dean.approvals') }}" class="nav-link @if(request()->routeIs('dean.approvals')) active @endif">
            <i class="bi bi-check2-circle"></i> Approvals
            @php $pendingCount = \App\Models\ApprovalWorkflow::where('approver_role','dean_academics')->where('status','pending')->count(); @endphp
            @if($pendingCount > 0)<span class="badge bg-warning text-dark ms-1">{{ $pendingCount }}</span>@endif
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Placement</div>
        <a href="{{ route('cmc.placement-stats') }}" class="nav-link @if(request()->routeIs('cmc.placement-stats')) active @endif">
            <i class="bi bi-bar-chart-line"></i> Placement Stats
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Reports</div>
        <a href="{{ route('admin.analytics') }}" class="nav-link @if(request()->routeIs('admin.analytics')) active @endif">
            <i class="bi bi-graph-up-arrow"></i> Analytics
        </a>
        <a href="{{ route('admin.aicte-report') }}" class="nav-link @if(request()->routeIs('admin.aicte-report*')) active @endif">
            <i class="bi bi-file-earmark-bar-graph"></i> AICTE Report
        </a>

        @endhasrole

        {{-- ===================== HOD ===================== --}}
        @hasrole('hod')

        <div class="section-label">Main</div>
        <a href="{{ route('hod.dashboard') }}" class="nav-link @if(request()->routeIs('hod.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Faculty</div>
        <a href="{{ route('hod.faculty.roster') }}" class="nav-link @if(request()->routeIs('hod.faculty.roster') || request()->routeIs('hod.faculty.roster.alias')) active @endif">
            <i class="bi bi-people"></i> Faculty Roster
        </a>
        <a href="{{ route('hod.faculty.workload') }}" class="nav-link @if(request()->routeIs('hod.faculty.workload')) active @endif">
            <i class="bi bi-bar-chart"></i> Faculty Workload
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Students</div>
        <a href="{{ route('hod.leaves') }}" class="nav-link @if(request()->routeIs('hod.leaves*')) active @endif">
            <i class="bi bi-calendar-x"></i> Leave Approvals
        </a>
        <a href="{{ route('hod.department-performance') }}" class="nav-link @if(request()->routeIs('hod.department-performance')) active @endif">
            <i class="bi bi-graph-up"></i> Dept Performance
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Grievances</div>
        <a href="{{ route('hod.grievances.index') }}" class="nav-link @if(request()->routeIs('hod.grievances.*')) active @endif">
            <i class="bi bi-chat-square-text"></i> Student Grievances
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Approvals</div>
        <a href="{{ route('hod.approvals') }}" class="nav-link @if(request()->routeIs('hod.approvals')) active @endif">
            <i class="bi bi-check2-circle"></i> Approvals
            @php $pendingCount = \App\Models\ApprovalWorkflow::where('approver_role','hod')->where('status','pending')->count(); @endphp
            @if($pendingCount > 0)<span class="badge bg-warning text-dark ms-1">{{ $pendingCount }}</span>@endif
        </a>

        @endhasrole

        {{-- ===================== PROGRAM CHAIR ===================== --}}
        @hasrole('program_chair')

        <div class="section-label">Main</div>
        <a href="{{ route('chair.dashboard') }}" class="nav-link @if(request()->routeIs('chair.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Curriculum</div>
        <a href="{{ route('chair.curriculum.index') }}" class="nav-link @if(request()->routeIs('chair.curriculum.*')) active @endif">
            <i class="bi bi-journal-bookmark"></i> Curriculum
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Timetable</div>
        <a href="{{ route('chair.timetable.builder') }}" class="nav-link @if(request()->routeIs('chair.timetable.*')) active @endif">
            <i class="bi bi-grid-3x3"></i> Timetable Builder
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Students</div>
        <a href="{{ route('chair.students.at-risk') }}" class="nav-link @if(request()->routeIs('chair.students.at-risk')) active @endif">
            <i class="bi bi-exclamation-triangle"></i> At-Risk Students
        </a>
        <a href="{{ route('chair.students.leaves') }}" class="nav-link @if(request()->routeIs('chair.students.leaves')) active @endif">
            <i class="bi bi-calendar-x"></i> Leave Approvals
        </a>
        <a href="{{ route('chair.students.condonations') }}" class="nav-link @if(request()->routeIs('chair.students.condonations')) active @endif">
            <i class="bi bi-shield-check"></i> Condonations
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Faculty</div>
        <a href="{{ route('chair.faculty.workload') }}" class="nav-link @if(request()->routeIs('chair.faculty.*')) active @endif">
            <i class="bi bi-person-badge"></i> Faculty Workload
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Reports</div>
        <a href="{{ route('chair.reports.subject-performance') }}" class="nav-link @if(request()->routeIs('chair.reports.*')) active @endif">
            <i class="bi bi-bar-chart-line"></i> Subject Performance
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Approvals</div>
        <a href="{{ route('chair.approvals') }}" class="nav-link @if(request()->routeIs('chair.approvals')) active @endif">
            <i class="bi bi-check2-circle"></i> Approvals
            @php $pendingCount = \App\Models\ApprovalWorkflow::where('approver_role','program_chair')->where('status','pending')->count(); @endphp
            @if($pendingCount > 0)<span class="badge bg-warning text-dark ms-1">{{ $pendingCount }}</span>@endif
        </a>

        @endhasrole

        {{-- ===================== EXAM CELL ===================== --}}
        @hasrole('exam_cell')

        <div class="section-label">Main</div>
        <a href="{{ route('exam-cell.dashboard') }}" class="nav-link @if(request()->routeIs('exam-cell.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Exams</div>
        <a href="{{ route('exam-cell.exams') }}" class="nav-link @if(request()->routeIs('exam-cell.exams') && !request()->routeIs('exam-cell.exams.create')) active @endif">
            <i class="bi bi-file-earmark-text"></i> All Exams
        </a>
        <a href="{{ route('exam-cell.exams.create') }}" class="nav-link @if(request()->routeIs('exam-cell.exams.create')) active @endif">
            <i class="bi bi-plus-circle"></i> Schedule Exam
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Results</div>
        <a href="{{ route('exam-cell.results') }}" class="nav-link @if(request()->routeIs('exam-cell.results')) active @endif">
            <i class="bi bi-award"></i> Results
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Admin</div>
        <a href="{{ route('exam-cell.hall-tickets') }}" class="nav-link @if(request()->routeIs('exam-cell.hall-tickets*')) active @endif">
            <i class="bi bi-ticket-perforated"></i> Hall Tickets
        </a>
        <a href="{{ route('exam-cell.marks-appeals') }}" class="nav-link @if(request()->routeIs('exam-cell.marks-appeals*')) active @endif">
            <i class="bi bi-envelope-exclamation"></i> Marks Appeals
        </a>
        <a href="{{ route('academic.transcripts.index') }}" class="nav-link @if(request()->routeIs('academic.transcripts.*')) active @endif">
            <i class="bi bi-file-earmark-text"></i> Transcripts
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Anomalies</div>
        <a href="{{ route('exam-cell.anomalies.index') }}" class="nav-link @if(request()->routeIs('exam-cell.anomalies.*')) active @endif">
            <i class="bi bi-exclamation-triangle"></i> Anomaly Log
        </a>

        @endhasrole

        {{-- ===================== ACCOUNTS OFFICER ===================== --}}
        @hasrole('accounts_officer')

        <div class="section-label">Main</div>
        <a href="{{ route('accounts.dashboard') }}" class="nav-link @if(request()->routeIs('accounts.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Fee Collections</div>
        <a href="{{ route('accounts.fee-collections') }}" class="nav-link @if(request()->routeIs('accounts.fee-collections')) active @endif">
            <i class="bi bi-cash-coin"></i> Fee Collections
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Payments</div>
        <a href="{{ route('accounts.admission-payments') }}" class="nav-link @if(request()->routeIs('accounts.admission-payments')) active @endif">
            <i class="bi bi-credit-card"></i> Admission Payments
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Finance</div>
        <a href="{{ route('accounts.outstanding') }}" class="nav-link @if(request()->routeIs('accounts.outstanding')) active @endif">
            <i class="bi bi-exclamation-circle"></i> Outstanding
        </a>
        <a href="{{ route('accounts.reconciliation') }}" class="nav-link {{ request()->routeIs('accounts.reconciliation') ? 'active' : '' }}">
            <i class="bi bi-arrow-left-right"></i> Reconciliation
        </a>
        <a href="{{ route('accounts.reports') }}" class="nav-link @if(request()->routeIs('accounts.reports')) active @endif">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>

        @endhasrole

        {{-- ===================== CMC ===================== --}}
        @hasrole('cmc')

        <div class="section-label">Main</div>
        <a href="{{ route('cmc.dashboard') }}" class="nav-link @if(request()->routeIs('cmc.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Placement</div>
        <a href="{{ route('cmc.drives') }}" class="nav-link @if(request()->routeIs('cmc.drives*')) active @endif">
            <i class="bi bi-briefcase"></i> Placement Drives
        </a>
        <a href="{{ route('cmc.companies') }}" class="nav-link @if(request()->routeIs('cmc.companies*')) active @endif">
            <i class="bi bi-building"></i> Companies
        </a>
        <a href="{{ route('cmc.events') }}" class="nav-link @if(request()->routeIs('cmc.events*')) active @endif">
            <i class="bi bi-calendar-event"></i> Career Events
        </a>
        <a href="{{ route('cmc.placement-stats') }}" class="nav-link @if(request()->routeIs('cmc.placement-stats')) active @endif">
            <i class="bi bi-bar-chart-line"></i> Placement Stats
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Students</div>
        <a href="{{ route('cmc.internships.index') }}" class="nav-link @if(request()->routeIs('cmc.internships.*')) active @endif">
            <i class="bi bi-laptop"></i> Internships
        </a>
        <a href="{{ route('cmc.alumni.index') }}" class="nav-link @if(request()->routeIs('cmc.alumni.*')) active @endif">
            <i class="bi bi-people-fill"></i> Alumni Database
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Analytics</div>
        <a href="{{ route('cmc.analytics') }}" class="nav-link @if(request()->routeIs('cmc.analytics')) active @endif">
            <i class="bi bi-pie-chart"></i> Analytics
        </a>

        @endhasrole

        {{-- ===================== DIRECTOR ===================== --}}
        @hasrole('director')

        <div class="section-label">Main</div>
        <a href="{{ route('director.dashboard') }}" class="nav-link @if(request()->routeIs('director.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Academics</div>
        <a href="{{ route('director.programs') }}" class="nav-link @if(request()->routeIs('director.programs')) active @endif">
            <i class="bi bi-mortarboard"></i> Programs
        </a>
        <a href="{{ route('director.reports') }}" class="nav-link @if(request()->routeIs('director.reports')) active @endif">
            <i class="bi bi-file-earmark-bar-graph"></i> Reports
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Analytics</div>
        <a href="{{ route('admin.analytics') }}" class="nav-link @if(request()->routeIs('admin.analytics')) active @endif">
            <i class="bi bi-graph-up-arrow"></i> Analytics
        </a>
        <a href="{{ route('admin.institutional-kpi') }}" class="nav-link @if(request()->routeIs('admin.institutional-kpi')) active @endif">
            <i class="bi bi-speedometer2"></i> Institutional KPI
        </a>
        <a href="{{ route('admin.aicte-report') }}" class="nav-link @if(request()->routeIs('admin.aicte-report*')) active @endif">
            <i class="bi bi-file-earmark-bar-graph"></i> AICTE Report
        </a>

        @endhasrole

        {{-- ===================== ADMISSION HEAD / OFFICER ===================== --}}
        @hasanyrole('admission_head|admission_officer')

        <div class="section-label">Main</div>
        <a href="{{ route('admission.dashboard') }}" class="nav-link @if(request()->routeIs('admission.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Applications</div>
        <a href="{{ route('admission.applicants.index') }}" class="nav-link @if(request()->routeIs('admission.applicants.*')) active @endif">
            <i class="bi bi-person-lines-fill"></i> Applicants
        </a>
        <a href="{{ route('admission.documents.queue') }}" class="nav-link @if(request()->routeIs('admission.documents.*')) active @endif">
            <i class="bi bi-folder-check"></i> Document Queue
            @php $docsPending = \App\Models\ApplicantDocument::where('status','pending')->count(); @endphp
            @if($docsPending > 0)<span class="badge bg-warning text-dark ms-1">{{ $docsPending }}</span>@endif
        </a>
        <a href="{{ route('admission.payments.queue') }}" class="nav-link @if(request()->routeIs('admission.payments.queue')) active @endif">
            <i class="bi bi-cash-coin"></i> Payment Queue
            @php $paymentsPending = \App\Models\AdmissionPayment::where('status','pending')->count(); @endphp
            @if($paymentsPending > 0)<span class="badge bg-info text-dark ms-1">{{ $paymentsPending }}</span>@endif
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Process</div>
        @if($firstProgram)
        <a href="{{ route('admission.merit-list.index', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.merit-list.*')) active @endif">
            <i class="bi bi-list-ol"></i> Merit List
        </a>
        <a href="{{ route('admission.offer-letters.index', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.offer-letters.*')) active @endif">
            <i class="bi bi-envelope-open"></i> Offer Letters
        </a>
        @endif
        <a href="{{ route('admission.enrollment.index') }}" class="nav-link @if(request()->routeIs('admission.enrollment.*')) active @endif">
            <i class="bi bi-person-check-fill"></i> Enrollments
        </a>
        <a href="{{ route('admission.sessions.index') }}" class="nav-link @if(request()->routeIs('admission.sessions.*')) active @endif">
            <i class="bi bi-calendar-event"></i> Sessions
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Leads</div>
        <a href="{{ route('admission.leads.index') }}" class="nav-link @if(request()->routeIs('admission.leads.index') || request()->routeIs('admission.leads.show')) active @endif">
            <i class="bi bi-funnel"></i> All Leads
            @php $newLeads = \App\Models\Lead::where('status','new')->count(); @endphp
            @if($newLeads > 0)<span class="badge bg-info text-dark ms-1">{{ $newLeads }}</span>@endif
        </a>
        <a href="{{ route('admission.leads.import') }}" class="nav-link @if(request()->routeIs('admission.leads.import')) active @endif">
            <i class="bi bi-upload"></i> Import Leads
        </a>
        <a href="{{ route('admission.leads.follow-ups.calendar') }}" class="nav-link @if(request()->routeIs('admission.leads.follow-ups.*')) active @endif">
            <i class="bi bi-calendar3"></i> Follow-up Calendar
        </a>
        <a href="{{ route('admission.leads.analytics') }}" class="nav-link @if(request()->routeIs('admission.leads.analytics')) active @endif">
            <i class="bi bi-graph-up-arrow"></i> Lead Analytics
        </a>

        <div class="sidebar-divider"></div>
        <div class="section-label">Reports</div>
        <a href="{{ route('admission.reports.index') }}" class="nav-link @if(request()->routeIs('admission.reports.*')) active @endif">
            <i class="bi bi-bar-chart-line"></i> Admission Reports
        </a>
        <a href="{{ route('admission.bulk-communication.index') }}" class="nav-link @if(request()->routeIs('admission.bulk-communication.*')) active @endif">
            <i class="bi bi-megaphone"></i> Bulk Communication
        </a>
        <a href="{{ route('admission.refunds.index') }}" class="nav-link @if(request()->routeIs('admission.refunds.*')) active @endif">
            <i class="bi bi-arrow-counterclockwise"></i> Refunds
        </a>

        @endhasanyrole

    </div>
</div>

{{-- ===== MOBILE OFFCANVAS SIDEBAR ===== --}}
<div class="offcanvas offcanvas-start sidebar-mobile" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" style="width:270px">
    <div class="offcanvas-header py-0" style="height:var(--topbar-height);background:rgba(0,0,0,.25);">
        <div class="d-flex align-items-center gap-2">
            <span style="width:30px;height:30px;background:var(--clr-primary);border-radius:7px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;"><i class="bi bi-mortarboard-fill"></i></span>
            <div>
                <div style="color:#f8fafc;font-weight:700;font-size:.9rem;line-height:1.2;" id="mobileSidebarLabel">{{ $brandName }}</div>
                <div style="color:rgba(255,255,255,.45);font-size:.65rem;">{{ $brandSub }}</div>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 pb-4">

        {{-- ===================== ADMIN MOBILE ===================== --}}
        @hasrole('admin')
        <div class="section-label">Main</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-link @if(request()->routeIs('admin.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Academic Setup</div>
        <a href="{{ route('admin.academic-years.index') }}" class="nav-link @if(request()->routeIs('admin.academic-years.*')) active @endif"><i class="bi bi-calendar3"></i> Academic Years</a>
        <a href="{{ route('admin.semesters.index') }}" class="nav-link @if(request()->routeIs('admin.semesters.*')) active @endif"><i class="bi bi-calendar-range"></i> Semesters</a>
        <a href="{{ route('admin.departments.index') }}" class="nav-link @if(request()->routeIs('admin.departments.*')) active @endif"><i class="bi bi-building"></i> Departments</a>
        <a href="{{ route('admin.subjects.index') }}" class="nav-link @if(request()->routeIs('admin.subjects.*')) active @endif"><i class="bi bi-book"></i> Subjects</a>
        <a href="{{ route('admin.classrooms.index') }}" class="nav-link @if(request()->routeIs('admin.classrooms.*')) active @endif"><i class="bi bi-door-open"></i> Classrooms</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Academic Structure</div>
        <a href="{{ route('admin.programs.index') }}" class="nav-link @if(request()->routeIs('admin.programs.*') || request()->routeIs('admin.admission-config.*')) active @endif"><i class="bi bi-mortarboard"></i> Programs</a>
        <a href="{{ route('admin.batches.index') }}" class="nav-link @if(request()->routeIs('admin.batches.*')) active @endif"><i class="bi bi-collection"></i> Batches</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Timetable</div>
        <a href="{{ route('admin.timetable-slots.index') }}" class="nav-link @if(request()->routeIs('admin.timetable-slots.*')) active @endif"><i class="bi bi-clock"></i> Time Slots</a>
        <a href="{{ route('admin.timetable.index') }}" class="nav-link @if(request()->routeIs('admin.timetable.index')||request()->routeIs('admin.timetable.create')||request()->routeIs('admin.timetable.edit')) active @endif"><i class="bi bi-grid-3x3-gap"></i> Weekly Timetable</a>
        <a href="{{ route('admin.timetable.teacher-view') }}" class="nav-link @if(request()->routeIs('admin.timetable.teacher-view')) active @endif"><i class="bi bi-person-lines-fill"></i> Teacher View</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">People</div>
        <a href="{{ route('admin.teachers.index') }}" class="nav-link @if(request()->routeIs('admin.teachers.*')) active @endif"><i class="bi bi-person-badge"></i> Teachers</a>
        <a href="{{ route('admin.students.index') }}" class="nav-link @if(request()->routeIs('admin.students.*')) active @endif"><i class="bi bi-people"></i> Students</a>
        <a href="{{ route('admin.applicants.index') }}" class="nav-link @if(request()->routeIs('admin.applicants.*')) active @endif"><i class="bi bi-person-lines-fill"></i> Applications</a>
        <a href="{{ route('admin.admissions.index') }}" class="nav-link @if(request()->routeIs('admin.admissions.*')) active @endif"><i class="bi bi-person-plus-fill"></i> Admissions</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Admission CRM</div>
        <a href="{{ route('admission.dashboard') }}" class="nav-link @if(request()->routeIs('admission.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> CRM Dashboard</a>
        <a href="{{ route('admission.applicants.index') }}" class="nav-link @if(request()->routeIs('admission.applicants.*')) active @endif"><i class="bi bi-person-lines-fill"></i> Applicants</a>
        <a href="{{ route('admission.documents.queue') }}" class="nav-link @if(request()->routeIs('admission.documents.*')) active @endif"><i class="bi bi-folder-check"></i> Document Queue</a>
        <a href="{{ route('admission.payments.queue') }}" class="nav-link @if(request()->routeIs('admission.payments.queue')) active @endif"><i class="bi bi-cash-coin"></i> Payment Queue</a>
        @if($firstProgram)
        <a href="{{ route('admission.merit-list.index', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.merit-list.*')) active @endif"><i class="bi bi-list-ol"></i> Merit List</a>
        <a href="{{ route('admission.offer-letters.index', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.offer-letters.*')) active @endif"><i class="bi bi-envelope-open"></i> Offer Letters</a>
        @endif
        <a href="{{ route('admission.enrollment.index') }}" class="nav-link @if(request()->routeIs('admission.enrollment.*')) active @endif"><i class="bi bi-person-check-fill"></i> Enrollments</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Leads</div>
        <a href="{{ route('admission.leads.index') }}" class="nav-link @if(request()->routeIs('admission.leads.index')||request()->routeIs('admission.leads.show')) active @endif"><i class="bi bi-funnel"></i> All Leads</a>
        <a href="{{ route('admission.leads.import') }}" class="nav-link @if(request()->routeIs('admission.leads.import')) active @endif"><i class="bi bi-upload"></i> Import Leads</a>
        <a href="{{ route('admission.leads.follow-ups.calendar') }}" class="nav-link @if(request()->routeIs('admission.leads.follow-ups.*')) active @endif"><i class="bi bi-calendar3"></i> Follow-up Calendar</a>
        <a href="{{ route('admission.reports.index') }}" class="nav-link @if(request()->routeIs('admission.reports.*')) active @endif"><i class="bi bi-bar-chart-line"></i> Admission Reports</a>
        <a href="{{ route('admission.bulk-communication.index') }}" class="nav-link @if(request()->routeIs('admission.bulk-communication.*')) active @endif"><i class="bi bi-megaphone"></i> Bulk Communication</a>
        <a href="{{ route('admission.refunds.index') }}" class="nav-link @if(request()->routeIs('admission.refunds.*')) active @endif"><i class="bi bi-arrow-counterclockwise"></i> Refunds</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Academics</div>
        <a href="{{ route('admin.attendance.index') }}" class="nav-link @if(request()->routeIs('admin.attendance.*')) active @endif"><i class="bi bi-check2-square"></i> Attendance</a>
        <a href="{{ route('admin.exams.index') }}" class="nav-link @if(request()->routeIs('admin.exams.*')) active @endif"><i class="bi bi-file-earmark-text"></i> Exams &amp; Results</a>
        <a href="{{ route('admin.results.index') }}" class="nav-link @if(request()->routeIs('admin.results.*')) active @endif"><i class="bi bi-award"></i> Grade Reports</a>
        <a href="{{ route('academic.transcripts.index') }}" class="nav-link @if(request()->routeIs('academic.transcripts.*')) active @endif"><i class="bi bi-file-earmark-text"></i> Transcripts</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Finance</div>
        <a href="{{ route('admin.fees.index') }}" class="nav-link @if(request()->routeIs('admin.fees.index')) active @endif"><i class="bi bi-cash-coin"></i> Fees</a>
        <a href="{{ route('admin.fees.report') }}" class="nav-link @if(request()->routeIs('admin.fees.report')) active @endif"><i class="bi bi-graph-up"></i> Fee Report</a>
        <a href="{{ route('accounts.dashboard') }}" class="nav-link @if(request()->routeIs('accounts.dashboard')) active @endif"><i class="bi bi-cash-stack"></i> Accounts</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Placement</div>
        <a href="{{ route('admin.companies.index') }}" class="nav-link @if(request()->routeIs('admin.companies.*')) active @endif"><i class="bi bi-building"></i> Companies</a>
        <a href="{{ route('admin.placement-drives.index') }}" class="nav-link @if(request()->routeIs('admin.placement-drives.*')) active @endif"><i class="bi bi-briefcase"></i> Drives</a>
        <a href="{{ route('cmc.placement-stats') }}" class="nav-link @if(request()->routeIs('cmc.placement-stats')) active @endif"><i class="bi bi-bar-chart-line"></i> Placement Stats</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Communication</div>
        <a href="{{ route('admin.notices.index') }}" class="nav-link @if(request()->routeIs('admin.notices.*')) active @endif"><i class="bi bi-megaphone"></i> Notices</a>
        <a href="{{ route('admin.bulk-mail.index') }}" class="nav-link @if(request()->routeIs('admin.bulk-mail.*')) active @endif"><i class="bi bi-envelope-paper"></i> Bulk Mail</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Approvals</div>
        <a href="{{ route('approvals.inbox') }}" class="nav-link @if(request()->routeIs('approvals.*')) active @endif"><i class="bi bi-check2-circle"></i> My Approvals</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Access Control</div>
        <a href="{{ route('admin.roles.hierarchy') }}" class="nav-link @if(request()->routeIs('admin.roles.hierarchy')) active @endif"><i class="bi bi-diagram-3"></i> Role Hierarchy</a>
        <a href="{{ route('admin.roles.permissions.index') }}" class="nav-link @if(request()->routeIs('admin.roles.permissions.*')) active @endif"><i class="bi bi-key"></i> Permissions</a>
        <a href="{{ route('admin.users.roles.index') }}" class="nav-link @if(request()->routeIs('admin.users.roles.*')) active @endif"><i class="bi bi-person-badge"></i> Role Assignments</a>
        <a href="{{ route('admin.org-hierarchy.index') }}" class="nav-link @if(request()->routeIs('admin.org-hierarchy.*')) active @endif"><i class="bi bi-diagram-3-fill"></i> Org Hierarchy</a>
        <a href="{{ route('admin.audit.index') }}" class="nav-link @if(request()->routeIs('admin.audit.*')) active @endif"><i class="bi bi-journal-text"></i> Audit Log</a>
        <a href="{{ route('admin.grievances.index') }}" class="nav-link @if(request()->routeIs('admin.grievances.*')) active @endif"><i class="bi bi-shield-exclamation"></i> Grievances</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">System</div>
        <a href="{{ route('admin.analytics') }}" class="nav-link @if(request()->routeIs('admin.analytics')) active @endif"><i class="bi bi-graph-up-arrow"></i> Analytics</a>
        <a href="{{ route('admin.institutional-kpi') }}" class="nav-link @if(request()->routeIs('admin.institutional-kpi')) active @endif"><i class="bi bi-speedometer2"></i> Institutional KPI</a>
        <a href="{{ route('admin.settings') }}" class="nav-link @if(request()->routeIs('admin.settings*')) active @endif"><i class="bi bi-gear"></i> Settings</a>
        <a href="{{ route('admin.activity-log') }}" class="nav-link @if(request()->routeIs('admin.activity-log')) active @endif"><i class="bi bi-clock-history"></i> Activity Log</a>
        @endhasrole

        {{-- ===================== DEAN ACADEMICS MOBILE ===================== --}}
        @hasrole('dean_academics')
        <div class="section-label">Main</div>
        <a href="{{ route('dean.dashboard') }}" class="nav-link @if(request()->routeIs('dean.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Academic Oversight</div>
        <a href="{{ route('dean.academics') }}" class="nav-link @if(request()->routeIs('dean.academics')) active @endif"><i class="bi bi-mortarboard-fill"></i> Academics</a>
        <a href="{{ route('dean.programs') }}" class="nav-link @if(request()->routeIs('dean.programs')) active @endif"><i class="bi bi-mortarboard"></i> Programs</a>
        <a href="{{ route('dean.students') }}" class="nav-link @if(request()->routeIs('dean.students')) active @endif"><i class="bi bi-people"></i> Students</a>
        <a href="{{ route('dean.attendance') }}" class="nav-link @if(request()->routeIs('dean.attendance')) active @endif"><i class="bi bi-check2-square"></i> Attendance</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Curriculum</div>
        <a href="{{ route('academic.curriculum-changes.index') }}" class="nav-link @if(request()->routeIs('academic.curriculum-changes.*')) active @endif"><i class="bi bi-journal-text"></i> Curriculum Changes</a>
        <a href="{{ route('academic.obe.co.index') }}" class="nav-link @if(request()->routeIs('academic.obe.*')) active @endif"><i class="bi bi-diagram-3 me-1"></i>OBE Framework</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Exams</div>
        <a href="{{ route('exam-cell.exams') }}" class="nav-link @if(request()->routeIs('exam-cell.exams') && !request()->routeIs('exam-cell.exams.create')) active @endif"><i class="bi bi-file-earmark-text"></i> Exams</a>
        <a href="{{ route('academic.transcripts.index') }}" class="nav-link @if(request()->routeIs('academic.transcripts.*')) active @endif"><i class="bi bi-file-earmark-text"></i> Transcripts</a>
        <a href="{{ route('exam-cell.hall-tickets') }}" class="nav-link @if(request()->routeIs('exam-cell.hall-tickets*')) active @endif"><i class="bi bi-ticket-perforated"></i> Hall Tickets</a>
        <a href="{{ route('exam-cell.marks-appeals') }}" class="nav-link @if(request()->routeIs('exam-cell.marks-appeals*')) active @endif"><i class="bi bi-envelope-exclamation"></i> Marks Appeals</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Approvals</div>
        <a href="{{ route('dean.approvals') }}" class="nav-link @if(request()->routeIs('dean.approvals')) active @endif"><i class="bi bi-check2-circle"></i> Approvals</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Reports</div>
        <a href="{{ route('admin.analytics') }}" class="nav-link @if(request()->routeIs('admin.analytics')) active @endif"><i class="bi bi-graph-up-arrow"></i> Analytics</a>
        <a href="{{ route('cmc.placement-stats') }}" class="nav-link @if(request()->routeIs('cmc.placement-stats')) active @endif"><i class="bi bi-bar-chart-line"></i> Placement Stats</a>
        @endhasrole

        {{-- ===================== HOD MOBILE ===================== --}}
        @hasrole('hod')
        <div class="section-label">Main</div>
        <a href="{{ route('hod.dashboard') }}" class="nav-link @if(request()->routeIs('hod.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Faculty</div>
        <a href="{{ route('hod.faculty.roster') }}" class="nav-link @if(request()->routeIs('hod.faculty.roster') || request()->routeIs('hod.faculty.roster.alias')) active @endif"><i class="bi bi-people"></i> Faculty Roster</a>
        <a href="{{ route('hod.faculty.workload') }}" class="nav-link @if(request()->routeIs('hod.faculty.workload')) active @endif"><i class="bi bi-bar-chart"></i> Faculty Workload</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Students</div>
        <a href="{{ route('hod.leaves') }}" class="nav-link @if(request()->routeIs('hod.leaves*')) active @endif"><i class="bi bi-calendar-x"></i> Leave Approvals</a>
        <a href="{{ route('hod.department-performance') }}" class="nav-link @if(request()->routeIs('hod.department-performance')) active @endif"><i class="bi bi-graph-up"></i> Dept Performance</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Grievances</div>
        <a href="{{ route('hod.grievances.index') }}" class="nav-link @if(request()->routeIs('hod.grievances.*')) active @endif"><i class="bi bi-chat-square-text"></i> Student Grievances</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Approvals</div>
        <a href="{{ route('hod.approvals') }}" class="nav-link @if(request()->routeIs('hod.approvals')) active @endif"><i class="bi bi-check2-circle"></i> Approvals</a>
        @endhasrole

        {{-- ===================== PROGRAM CHAIR MOBILE ===================== --}}
        @hasrole('program_chair')
        <div class="section-label">Main</div>
        <a href="{{ route('chair.dashboard') }}" class="nav-link @if(request()->routeIs('chair.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Curriculum</div>
        <a href="{{ route('chair.curriculum.index') }}" class="nav-link @if(request()->routeIs('chair.curriculum.*')) active @endif"><i class="bi bi-journal-bookmark"></i> Curriculum</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Timetable</div>
        <a href="{{ route('chair.timetable.builder') }}" class="nav-link @if(request()->routeIs('chair.timetable.*')) active @endif"><i class="bi bi-grid-3x3"></i> Timetable Builder</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Students</div>
        <a href="{{ route('chair.students.at-risk') }}" class="nav-link @if(request()->routeIs('chair.students.at-risk')) active @endif"><i class="bi bi-exclamation-triangle"></i> At-Risk Students</a>
        <a href="{{ route('chair.students.leaves') }}" class="nav-link @if(request()->routeIs('chair.students.leaves')) active @endif"><i class="bi bi-calendar-x"></i> Leave Approvals</a>
        <a href="{{ route('chair.students.condonations') }}" class="nav-link @if(request()->routeIs('chair.students.condonations')) active @endif"><i class="bi bi-shield-check"></i> Condonations</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Faculty</div>
        <a href="{{ route('chair.faculty.workload') }}" class="nav-link @if(request()->routeIs('chair.faculty.*')) active @endif"><i class="bi bi-person-badge"></i> Faculty Workload</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Reports</div>
        <a href="{{ route('chair.reports.subject-performance') }}" class="nav-link @if(request()->routeIs('chair.reports.*')) active @endif"><i class="bi bi-bar-chart-line"></i> Subject Performance</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Approvals</div>
        <a href="{{ route('chair.approvals') }}" class="nav-link @if(request()->routeIs('chair.approvals')) active @endif"><i class="bi bi-check2-circle"></i> Approvals</a>
        @endhasrole

        {{-- ===================== EXAM CELL MOBILE ===================== --}}
        @hasrole('exam_cell')
        <div class="section-label">Main</div>
        <a href="{{ route('exam-cell.dashboard') }}" class="nav-link @if(request()->routeIs('exam-cell.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Exams</div>
        <a href="{{ route('exam-cell.exams') }}" class="nav-link @if(request()->routeIs('exam-cell.exams') && !request()->routeIs('exam-cell.exams.create')) active @endif"><i class="bi bi-file-earmark-text"></i> All Exams</a>
        <a href="{{ route('exam-cell.exams.create') }}" class="nav-link @if(request()->routeIs('exam-cell.exams.create')) active @endif"><i class="bi bi-plus-circle"></i> Schedule Exam</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Results</div>
        <a href="{{ route('exam-cell.results') }}" class="nav-link @if(request()->routeIs('exam-cell.results')) active @endif"><i class="bi bi-award"></i> Results</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Admin</div>
        <a href="{{ route('exam-cell.hall-tickets') }}" class="nav-link @if(request()->routeIs('exam-cell.hall-tickets*')) active @endif"><i class="bi bi-ticket-perforated"></i> Hall Tickets</a>
        <a href="{{ route('exam-cell.marks-appeals') }}" class="nav-link @if(request()->routeIs('exam-cell.marks-appeals*')) active @endif"><i class="bi bi-envelope-exclamation"></i> Marks Appeals</a>
        <a href="{{ route('academic.transcripts.index') }}" class="nav-link @if(request()->routeIs('academic.transcripts.*')) active @endif"><i class="bi bi-file-earmark-text"></i> Transcripts</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Anomalies</div>
        <a href="{{ route('exam-cell.anomalies.index') }}" class="nav-link @if(request()->routeIs('exam-cell.anomalies.*')) active @endif"><i class="bi bi-exclamation-triangle"></i> Anomaly Log</a>
        @endhasrole

        {{-- ===================== ACCOUNTS MOBILE ===================== --}}
        @hasrole('accounts_officer')
        <div class="section-label">Main</div>
        <a href="{{ route('accounts.dashboard') }}" class="nav-link @if(request()->routeIs('accounts.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Collections</div>
        <a href="{{ route('accounts.fee-collections') }}" class="nav-link @if(request()->routeIs('accounts.fee-collections')) active @endif"><i class="bi bi-cash-coin"></i> Fee Collections</a>
        <a href="{{ route('accounts.admission-payments') }}" class="nav-link @if(request()->routeIs('accounts.admission-payments')) active @endif"><i class="bi bi-credit-card"></i> Admission Payments</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Finance</div>
        <a href="{{ route('accounts.outstanding') }}" class="nav-link @if(request()->routeIs('accounts.outstanding')) active @endif"><i class="bi bi-exclamation-circle"></i> Outstanding</a>
        <a href="{{ route('accounts.reconciliation') }}" class="nav-link {{ request()->routeIs('accounts.reconciliation') ? 'active' : '' }}"><i class="bi bi-arrow-left-right"></i> Reconciliation</a>
        <a href="{{ route('accounts.reports') }}" class="nav-link @if(request()->routeIs('accounts.reports')) active @endif"><i class="bi bi-bar-chart-line"></i> Reports</a>
        @endhasrole

        {{-- ===================== CMC MOBILE ===================== --}}
        @hasrole('cmc')
        <div class="section-label">Main</div>
        <a href="{{ route('cmc.dashboard') }}" class="nav-link @if(request()->routeIs('cmc.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Placement</div>
        <a href="{{ route('cmc.drives') }}" class="nav-link @if(request()->routeIs('cmc.drives*')) active @endif"><i class="bi bi-briefcase"></i> Placement Drives</a>
        <a href="{{ route('cmc.companies') }}" class="nav-link @if(request()->routeIs('cmc.companies*')) active @endif"><i class="bi bi-building"></i> Companies</a>
        <a href="{{ route('cmc.events') }}" class="nav-link @if(request()->routeIs('cmc.events*')) active @endif"><i class="bi bi-calendar-event"></i> Career Events</a>
        <a href="{{ route('cmc.placement-stats') }}" class="nav-link @if(request()->routeIs('cmc.placement-stats')) active @endif"><i class="bi bi-bar-chart-line"></i> Placement Stats</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Students</div>
        <a href="{{ route('cmc.internships.index') }}" class="nav-link @if(request()->routeIs('cmc.internships.*')) active @endif"><i class="bi bi-laptop"></i> Internships</a>
        <a href="{{ route('cmc.alumni.index') }}" class="nav-link @if(request()->routeIs('cmc.alumni.*')) active @endif"><i class="bi bi-people-fill"></i> Alumni Database</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Analytics</div>
        <a href="{{ route('cmc.analytics') }}" class="nav-link @if(request()->routeIs('cmc.analytics')) active @endif"><i class="bi bi-pie-chart"></i> Analytics</a>
        @endhasrole

        {{-- ===================== DIRECTOR MOBILE ===================== --}}
        @hasrole('director')
        <div class="section-label">Main</div>
        <a href="{{ route('director.dashboard') }}" class="nav-link @if(request()->routeIs('director.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Academics</div>
        <a href="{{ route('director.programs') }}" class="nav-link @if(request()->routeIs('director.programs')) active @endif"><i class="bi bi-mortarboard"></i> Programs</a>
        <a href="{{ route('director.reports') }}" class="nav-link @if(request()->routeIs('director.reports')) active @endif"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Analytics</div>
        <a href="{{ route('admin.analytics') }}" class="nav-link @if(request()->routeIs('admin.analytics')) active @endif"><i class="bi bi-graph-up-arrow"></i> Analytics</a>
        <a href="{{ route('admin.institutional-kpi') }}" class="nav-link @if(request()->routeIs('admin.institutional-kpi')) active @endif"><i class="bi bi-speedometer2"></i> Institutional KPI</a>
        <a href="{{ route('admin.aicte-report') }}" class="nav-link @if(request()->routeIs('admin.aicte-report*')) active @endif"><i class="bi bi-file-earmark-bar-graph"></i> AICTE Report</a>
        @endhasrole

        {{-- ===================== ADMISSION MOBILE ===================== --}}
        @hasanyrole('admission_head|admission_officer')
        <div class="section-label">Main</div>
        <a href="{{ route('admission.dashboard') }}" class="nav-link @if(request()->routeIs('admission.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Applications</div>
        <a href="{{ route('admission.applicants.index') }}" class="nav-link @if(request()->routeIs('admission.applicants.*')) active @endif"><i class="bi bi-person-lines-fill"></i> Applicants</a>
        <a href="{{ route('admission.documents.queue') }}" class="nav-link @if(request()->routeIs('admission.documents.*')) active @endif"><i class="bi bi-folder-check"></i> Document Queue</a>
        <a href="{{ route('admission.payments.queue') }}" class="nav-link @if(request()->routeIs('admission.payments.queue')) active @endif"><i class="bi bi-cash-coin"></i> Payment Queue</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Process</div>
        @if($firstProgram)
        <a href="{{ route('admission.merit-list.index', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.merit-list.*')) active @endif"><i class="bi bi-list-ol"></i> Merit List</a>
        <a href="{{ route('admission.offer-letters.index', $firstProgram) }}" class="nav-link @if(request()->routeIs('admission.offer-letters.*')) active @endif"><i class="bi bi-envelope-open"></i> Offer Letters</a>
        @endif
        <a href="{{ route('admission.enrollment.index') }}" class="nav-link @if(request()->routeIs('admission.enrollment.*')) active @endif"><i class="bi bi-person-check-fill"></i> Enrollments</a>
        <a href="{{ route('admission.sessions.index') }}" class="nav-link @if(request()->routeIs('admission.sessions.*')) active @endif"><i class="bi bi-calendar-event"></i> Sessions</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Leads</div>
        <a href="{{ route('admission.leads.index') }}" class="nav-link @if(request()->routeIs('admission.leads.index')||request()->routeIs('admission.leads.show')) active @endif"><i class="bi bi-funnel"></i> All Leads</a>
        <a href="{{ route('admission.leads.import') }}" class="nav-link @if(request()->routeIs('admission.leads.import')) active @endif"><i class="bi bi-upload"></i> Import Leads</a>
        <a href="{{ route('admission.leads.follow-ups.calendar') }}" class="nav-link @if(request()->routeIs('admission.leads.follow-ups.*')) active @endif"><i class="bi bi-calendar3"></i> Follow-up Calendar</a>
        <a href="{{ route('admission.leads.analytics') }}" class="nav-link @if(request()->routeIs('admission.leads.analytics')) active @endif"><i class="bi bi-graph-up-arrow"></i> Lead Analytics</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Reports</div>
        <a href="{{ route('admission.reports.index') }}" class="nav-link @if(request()->routeIs('admission.reports.*')) active @endif"><i class="bi bi-bar-chart-line"></i> Admission Reports</a>
        <a href="{{ route('admission.bulk-communication.index') }}" class="nav-link @if(request()->routeIs('admission.bulk-communication.*')) active @endif"><i class="bi bi-megaphone"></i> Bulk Communication</a>
        <a href="{{ route('admission.refunds.index') }}" class="nav-link @if(request()->routeIs('admission.refunds.*')) active @endif"><i class="bi bi-arrow-counterclockwise"></i> Refunds</a>
        @endhasanyrole

    </div>
</div>

{{-- ===== MAIN CONTENT ===== --}}
<div class="main-content">

    {{-- TOPBAR --}}
    <div class="topbar">
        <div class="topbar-left">
            {{-- Mobile hamburger --}}
            <button class="btn btn-sm sidebar-mobile-toggle d-lg-none" type="button"
                    style="width:36px;height:36px;padding:0;border:1px solid var(--clr-border);border-radius:var(--radius-sm);background:transparent;color:var(--clr-text-muted);display:flex;align-items:center;justify-content:center;"
                    data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"
                    aria-controls="mobileSidebar" aria-label="Open navigation menu">
                <i class="bi bi-list fs-5"></i>
            </button>

            {{-- Page heading --}}
            <div class="page-heading">
                <h6>@yield('page-title', 'Dashboard')</h6>
                @hasSection('breadcrumb')
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        @yield('breadcrumb')
                    </ol>
                </nav>
                @endif
            </div>
        </div>

        <div class="topbar-right">
            {{-- Global search — admin only --}}
            @hasrole('admin')
            <form method="GET" action="{{ route('admin.search') }}" class="topbar-search d-none d-md-flex">
                <i class="bi bi-search search-icon"></i>
                <input type="search" name="q" placeholder="Search students, teachers..." value="{{ request('q') }}" aria-label="Global search">
            </form>
            @endhasrole

            {{-- Dark mode toggle --}}
            <button class="theme-btn" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark mode">
                <i class="bi bi-moon-fill" id="themeIcon"></i>
            </button>

            {{-- Notification bell --}}
            <a href="{{ route('notifications.index') }}" class="notif-btn" aria-label="Notifications" title="Notifications" id="notifBell">
                <i class="bi bi-bell"></i>
                <span class="notif-badge" id="notifBadge" style="display:none"></span>
            </a>

            {{-- User avatar dropdown --}}
            <div class="dropdown">
                <button class="user-avatar dropdown-toggle" style="border:none;" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User menu">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:200px;font-size:.84rem;border-color:var(--clr-border);">
                    <li>
                        <div class="px-3 py-2 border-bottom mb-1">
                            <div class="fw-bold" style="font-size:.875rem">{{ auth()->user()->name }}</div>
                            <div class="small text-muted">{{ auth()->user()->email }}</div>
                            <div class="mt-1">
                                <span class="badge" style="background:var(--clr-primary);font-size:.65rem">
                                    {{ ucwords(str_replace('_', ' ', auth()->user()->getRoleNames()->first() ?? 'User')) }}
                                </span>
                            </div>
                        </div>
                    </li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>View Profile</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- PAGE BODY --}}
    <div class="page-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show js-auto-dismiss" role="alert" aria-live="polite">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert" aria-live="polite">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif
        @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show js-auto-dismiss" role="alert" aria-live="polite">
                <i class="bi bi-info-circle me-2"></i>{{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert" aria-live="polite">
                <i class="bi bi-exclamation-circle me-2"></i>{{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert" aria-live="polite">
                <i class="bi bi-exclamation-triangle me-2"></i>
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif

        @yield('content')
    </div>
</div>

{{-- ===== DELETE CONFIRMATION MODAL ===== --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
        <div class="modal-content">
            <div class="modal-header pb-0">
                <div style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#dc2626;font-size:1.4rem;">
                    <i class="bi bi-trash3"></i>
                </div>
            </div>
            <div class="modal-body pt-2">
                <h6 class="fw-700 mb-1" id="deleteModalLabel" style="font-size:1rem;font-weight:700;">Delete Record</h6>
                <p class="mb-0" style="font-size:.85rem;color:var(--clr-text-muted);">
                    Are you sure you want to delete <strong id="deleteModalName"></strong>? This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer pt-0 gap-2">
                <button type="button" class="btn btn-sm" style="border:1px solid var(--clr-border);background:transparent;color:var(--clr-text);" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="bi bi-trash3 me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ── Dark mode ──────────────────────────────────────────────
(function () {
    var saved = localStorage.getItem('edumTheme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeIcon(saved);

    document.getElementById('themeToggle').addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('edumTheme', next);
        updateThemeIcon(next);
    });

    function updateThemeIcon(theme) {
        var icon = document.getElementById('themeIcon');
        if (!icon) return;
        icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
})();

// ── Auto-dismiss success/info alerts ──────────────────────
(function () {
    setTimeout(function () {
        document.querySelectorAll('.js-auto-dismiss').forEach(function (el) {
            var alert = bootstrap.Alert.getOrCreateInstance(el);
            alert.close();
        });
    }, 4000);
})();

// ── Delete modal ───────────────────────────────────────────
(function () {
    var deleteModal = document.getElementById('deleteModal');
    if (!deleteModal) return;

    deleteModal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) return;
        var action = trigger.getAttribute('data-action') || '#';
        var name   = trigger.getAttribute('data-name') || 'this record';

        document.getElementById('deleteForm').setAttribute('action', action);
        document.getElementById('deleteModalName').textContent = name;
    });

    // Support data-confirm-delete programmatic triggers
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm-delete="true"]');
        if (!btn) return;
        e.preventDefault();
        var action = btn.getAttribute('data-action') || '#';
        var name   = btn.getAttribute('data-name') || 'this record';
        document.getElementById('deleteForm').setAttribute('action', action);
        document.getElementById('deleteModalName').textContent = name;
        var bsModal = new bootstrap.Modal(deleteModal);
        bsModal.show();
    });
})();

// ── Notification bell unread count (polls every 60s) ────────
(function () {
    var badge = document.getElementById('notifBadge');
    if (!badge) return;
    function updateCount() {
        fetch('{{ route('notifications.unread-count') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    badge.style.display = '';
                    document.title = '(' + (data.unread_count > 99 ? '99+' : data.unread_count) + ') ' + document.title.replace(/^\(\d+\+?\) /, '');
                } else {
                    badge.style.display = 'none';
                    document.title = document.title.replace(/^\(\d+\+?\) /, '');
                }
            })
            .catch(function () {});
    }
    updateCount();
    setInterval(updateCount, 60000);
})();
</script>

@stack('scripts')
</body>
</html>
