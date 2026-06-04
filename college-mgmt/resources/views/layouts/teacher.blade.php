<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'College Management')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --sidebar-bg:#164e63; --sidebar-active:#155e75; --sidebar-text:#a5f3fc; }
        body { background:#f1f5f9; }
        .sidebar { width:260px; min-height:100vh; background:var(--sidebar-bg); position:fixed; top:0; left:0; overflow-y:auto; z-index:200; }
        .sidebar .brand { padding:1.1rem 1.4rem; background:#0c4a6e; color:#f8fafc; font-weight:700; font-size:1.05rem; display:flex; align-items:center; gap:.6rem; }
        .sidebar .nav-link { color:var(--sidebar-text); padding:.42rem 1.3rem; font-size:.84rem; border-radius:6px; margin:1px 8px; display:flex; align-items:center; gap:.5rem; }
        .sidebar .nav-link:hover,.sidebar .nav-link.active { background:var(--sidebar-active); color:#f1f5f9; }
        .sidebar .section-label { font-size:.68rem; text-transform:uppercase; letter-spacing:.1em; color:#0891b2; padding:.8rem 1.4rem .2rem; }
        .main-content { margin-left:260px; }
        .topbar { background:#fff; border-bottom:1px solid #e2e8f0; padding:.75rem 1.75rem; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:100; }
        .page-body { padding:1.5rem 1.75rem; }
        .card { border:none; box-shadow:0 1px 4px rgba(0,0,0,.07); border-radius:10px; }
        .card-header { background:#fff; border-bottom:1px solid #f1f5f9; font-weight:600; padding:.8rem 1.2rem; }
        .table th { font-size:.78rem; text-transform:uppercase; letter-spacing:.05em; color:#64748b; border-bottom:2px solid #e2e8f0; font-weight:600; }
        .timetable-grid th, .timetable-grid td { text-align:center; vertical-align:middle; min-width:110px; font-size:.78rem; padding:.4rem; }
        .timetable-cell { background:#dbeafe; border-radius:6px; padding:5px 7px; }
        .timetable-cell .subj { font-weight:700; color:#1e40af; font-size:.78rem; }
        .timetable-cell .tchr { color:#475569; font-size:.7rem; }
        .timetable-cell .room-tag { background:#bfdbfe; color:#1e40af; border-radius:4px; padding:1px 5px; font-size:.67rem; }
        .break-row td { background:#fef9c3; color:#713f12; font-size:.75rem; font-style:italic; }
        .day-header { background:#164e63; color:#f8fafc; font-size:.8rem; }
        .stat-card { border-radius:12px; padding:1.4rem; color:white; }
        @media(max-width:991.98px){
            .sidebar-desktop{display:none!important;}
            .main-content{margin-left:0;}
            .offcanvas-body .nav-link{color:var(--sidebar-text);padding:.42rem 1rem;font-size:.84rem;border-radius:6px;margin:1px 4px;display:flex;align-items:center;gap:.5rem;}
            .offcanvas-body .nav-link:hover,.offcanvas-body .nav-link.active{background:var(--sidebar-active);color:#f1f5f9;}
            .offcanvas-body .section-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.1em;color:#0891b2;padding:.6rem 1rem .1rem;}
        }
        @media(min-width:992px){.sidebar-mobile{display:none!important;}}
    </style>
    @stack('styles')
</head>
<body>

<div class="sidebar sidebar-desktop">
    <div class="brand"><i class="bi bi-person-badge-fill"></i> Teacher Portal</div>
    <div class="mt-1 pb-4">
        <div class="section-label">Main</div>
        <a href="{{ route('teacher.dashboard') }}" class="nav-link @if(request()->routeIs('teacher.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <div class="section-label">Academics</div>
        <a href="{{ route('teacher.attendance.mark') }}" class="nav-link @if(request()->routeIs('teacher.attendance.*')) active @endif">
            <i class="bi bi-check2-square"></i> Mark Attendance
        </a>
        <a href="{{ route('teacher.exams.index') }}" class="nav-link @if(request()->routeIs('teacher.exams.*')) active @endif">
            <i class="bi bi-pencil-square"></i> Enter Marks
        </a>
        <a href="{{ route('teacher.students.index') }}" class="nav-link @if(request()->routeIs('teacher.students.*')) active @endif">
            <i class="bi bi-people"></i> My Students
        </a>
    </div>
</div>

{{-- Mobile offcanvas sidebar --}}
<div class="offcanvas offcanvas-start sidebar-mobile" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" style="background:var(--sidebar-bg);width:270px">
    <div class="offcanvas-header border-bottom border-secondary py-2">
        <span class="text-white fw-bold" id="mobileSidebarLabel"><i class="bi bi-person-badge-fill me-2"></i>Teacher Portal</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 pb-4">
        <div class="section-label">Main</div>
        <a href="{{ route('teacher.dashboard') }}" class="nav-link @if(request()->routeIs('teacher.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="section-label">Academics</div>
        <a href="{{ route('teacher.attendance.mark') }}" class="nav-link @if(request()->routeIs('teacher.attendance.*')) active @endif"><i class="bi bi-check2-square"></i> Mark Attendance</a>
        <a href="{{ route('teacher.exams.index') }}" class="nav-link @if(request()->routeIs('teacher.exams.*')) active @endif"><i class="bi bi-pencil-square"></i> Enter Marks</a>
        <a href="{{ route('teacher.students.index') }}" class="nav-link @if(request()->routeIs('teacher.students.*')) active @endif"><i class="bi bi-people"></i> My Students</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary sidebar-mobile d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open navigation menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div>
                <h6 class="mb-0 fw-semibold">@yield('page-title', 'Dashboard')</h6>
                @hasSection('breadcrumb')
                <nav aria-label="breadcrumb" class="mt-1">
                    <ol class="breadcrumb breadcrumb-sm mb-0" style="font-size:.8rem">
                        @yield('breadcrumb')
                    </ol>
                </nav>
                @endif
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small d-none d-md-inline"><i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary" aria-label="Logout"><i class="bi bi-box-arrow-right"></i> <span class="d-none d-md-inline">Logout</span></button>
            </form>
        </div>
    </div>

    <div class="page-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                @foreach($errors->all() as $error) <div>{{ $error }}</div> @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
