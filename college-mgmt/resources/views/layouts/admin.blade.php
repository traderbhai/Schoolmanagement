<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0d6efd">
    <link rel="manifest" href="/manifest.json">
    <title>@yield('title', 'EduManage - Portal')</title>
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
        $brandRoute = 'academics.dean-os.index';
    } elseif ($user->hasRole('hod')) {
        $brandName  = 'HOD Portal';
        $brandSub   = 'Head of Department';
        $brandRoute = 'hod.dashboard';
    } elseif ($user->hasRole('program_chair')) {
        $brandName  = 'PMC Portal';
        $brandSub   = 'Program Management';
        $brandRoute = 'academics.pmc.command';
    } elseif ($user->hasRole('exam_cell')) {
        $brandName  = 'Exam Cell';
        $brandSub   = 'Examinations Office';
        $brandRoute = 'exam-cell.dashboard';
    } elseif ($user->hasAnyRole(['exam_manager', 'exam_officer'])) {
        $brandName  = 'CoE Portal';
        $brandSub   = 'Examinations Office';
        $brandRoute = 'academics.coe.index';
    } elseif ($user->hasAnyRole(['iqac_head', 'iqac_manager', 'iqac_officer'])) {
        $brandName  = 'IQAC Portal';
        $brandSub   = 'Quality Office';
        $brandRoute = 'academics.iqac.index';
    } elseif ($user->hasAnyRole(['pmc_head', 'pmc_manager', 'pmc_officer'])) {
        $brandName  = 'PMC Portal';
        $brandSub   = 'Program Management';
        $brandRoute = 'academics.pmc.command';
    } elseif ($user->hasAnyRole(['program_director', 'program_leader', 'semester_coordinator'])) {
        $brandName  = 'Program Office';
        $brandSub   = 'Program Leadership';
        $brandRoute = 'academics.program-leadership.index';
    } elseif ($user->hasAnyRole(['course_coordinator', 'faculty_mentor'])) {
        $brandName  = 'Course Delivery';
        $brandSub   = 'Academic Delivery';
        $brandRoute = 'academics.course-delivery.index';
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
    } elseif (app(\App\Services\DepartmentHierarchyService::class)->isAdmissionUser($user)) {
        $brandName  = 'Admissions';
        $brandSub   = 'CRM & Enrollment';
        $brandRoute = 'admission.dashboard';
    } else {
        $brandName  = 'EduManage';
        $brandSub   = 'Portal';
        $brandRoute = 'admin.dashboard';
    }

    $firstProgram = \App\Models\Program::where('is_active', true)->first();
    $departmentHierarchyService = app(\App\Services\DepartmentHierarchyService::class);
    $manageableDepartments = $departmentHierarchyService->manageableDepartments($user);
    $governanceDepartments = $departmentHierarchyService->manageableGovernanceDepartments($user);
    $showDepartmentHierarchyControl = $manageableDepartments->isNotEmpty()
        && !$user->hasRole('admin');
    $showDepartmentGovernanceControl = $governanceDepartments->isNotEmpty()
        && !$user->hasRole('admin');
    $showDepartmentControls = $showDepartmentHierarchyControl || $showDepartmentGovernanceControl;
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
        <x-ui.manifest-sidebar role="admin" brand-sub="Admin Portal" brand-icon="bi-grid-1x2-fill" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== DEAN ACADEMICS ===================== --}}
        @hasrole('dean_academics')

        <x-ui.manifest-sidebar role="dean" brand-sub="Academic Office" brand-icon="bi-command" :show-brand="false" :show-footer="false" />

        @endhasrole

        {{-- ===================== HOD ===================== --}}
        @hasrole('hod')
        <x-ui.manifest-sidebar role="hod" brand-sub="Head of Department" brand-icon="bi-building" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== PROGRAM CHAIR ===================== --}}
        @hasrole('program_chair')
        <x-ui.manifest-sidebar role="pmc" brand-sub="Program Management" brand-icon="bi-kanban" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== EXAM CELL ===================== --}}
        @if(auth()->user()?->hasAnyRole(['exam_cell','exam_manager','exam_officer']))
        <x-ui.manifest-sidebar role="coe" brand-sub="Examination Office" brand-icon="bi-clipboard2-data" :show-brand="false" :show-footer="false" />
        @endif

        @if(auth()->user()?->hasAnyRole(['program_director','program_leader','semester_coordinator','course_coordinator','faculty_mentor']))
        <x-ui.manifest-sidebar role="program_leadership" brand-sub="Program Office" brand-icon="bi-mortarboard" :show-brand="false" :show-footer="false" />
        @endif

        @if(auth()->user()?->hasAnyRole(['teacher','faculty']))
        <x-ui.manifest-sidebar role="teacher" brand-sub="Teacher Portal" brand-icon="bi-person-badge-fill" :show-brand="false" :show-footer="false" />
        @endif

        @if(auth()->user()?->hasAnyRole(['iqac_head','iqac_manager','iqac_officer']))
        <x-ui.manifest-sidebar role="iqac" brand-sub="Quality Office" brand-icon="bi-shield-check" :show-brand="false" :show-footer="false" />
        @endif

        {{-- ===================== ACCOUNTS OFFICER ===================== --}}
        @hasrole('accounts_officer')

        <x-ui.manifest-sidebar role="accounts" brand-sub="Finance Office" brand-icon="bi-cash-stack" :show-brand="false" :show-footer="false" />

        @endhasrole

        {{-- ===================== CMC ===================== --}}
        @hasrole('cmc')

        <x-ui.manifest-sidebar role="cmc" brand-sub="Placement & Careers" brand-icon="bi-briefcase-fill" :show-brand="false" :show-footer="false" />

        @endhasrole

        {{-- ===================== DIRECTOR ===================== --}}
        @hasrole('director')
        <x-ui.manifest-sidebar role="director" brand-sub="Institute Management" brand-icon="bi-person-badge" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== ADMISSION HEAD / OFFICER ===================== --}}
        @if($departmentHierarchyService->isAdmissionUser($user))
        <x-ui.manifest-sidebar role="admission" brand-sub="CRM & Enrollment" brand-icon="bi-person-plus-fill" :show-brand="false" :show-footer="false" />
        @endif

        @if($showDepartmentControls)
        <div class="sidebar-divider"></div>
        <div class="section-label">Department Controls</div>
        @if($showDepartmentHierarchyControl)
        <a href="{{ route('department-hierarchy.index') }}" class="nav-link @if(request()->routeIs('department-hierarchy.*')) active @endif">
            <i class="bi bi-person-workspace"></i> Department Hierarchy
        </a>
        @endif
        @if($showDepartmentGovernanceControl)
        <a href="{{ route('department-governance.index') }}" class="nav-link @if(request()->routeIs('department-governance.*')) active @endif">
            <i class="bi bi-sliders"></i> Department Governance
        </a>
        @endif
        @endif

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
        <x-ui.manifest-sidebar role="admin" brand-sub="Admin Portal" brand-icon="bi-grid-1x2-fill" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== DEAN ACADEMICS MOBILE ===================== --}}
        @hasrole('dean_academics')
        <x-ui.manifest-sidebar role="dean" brand-sub="Academic Office" brand-icon="bi-command" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== HOD MOBILE ===================== --}}
        @hasrole('hod')
        <x-ui.manifest-sidebar role="hod" brand-sub="Head of Department" brand-icon="bi-building" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== PROGRAM CHAIR MOBILE ===================== --}}
        @hasrole('program_chair')
        <x-ui.manifest-sidebar role="pmc" brand-sub="Program Management" brand-icon="bi-kanban" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== EXAM CELL MOBILE ===================== --}}
        @if(auth()->user()?->hasAnyRole(['exam_cell','exam_manager','exam_officer']))
        <x-ui.manifest-sidebar role="coe" brand-sub="Examination Office" brand-icon="bi-clipboard2-data" :show-brand="false" :show-footer="false" />
        @endif

        @if(auth()->user()?->hasAnyRole(['program_director','program_leader','semester_coordinator','course_coordinator','faculty_mentor']))
        <x-ui.manifest-sidebar role="program_leadership" brand-sub="Program Office" brand-icon="bi-mortarboard" :show-brand="false" :show-footer="false" />
        @endif

        @if(auth()->user()?->hasAnyRole(['teacher','faculty']))
        <x-ui.manifest-sidebar role="teacher" brand-sub="Teacher Portal" brand-icon="bi-person-badge-fill" :show-brand="false" :show-footer="false" />
        @endif

        @if(auth()->user()?->hasAnyRole(['iqac_head','iqac_manager','iqac_officer']))
        <x-ui.manifest-sidebar role="iqac" brand-sub="Quality Office" brand-icon="bi-shield-check" :show-brand="false" :show-footer="false" />
        @endif

        {{-- ===================== ACCOUNTS MOBILE ===================== --}}
        @hasrole('accounts_officer')
        <x-ui.manifest-sidebar role="accounts" brand-sub="Finance Office" brand-icon="bi-cash-stack" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== CMC MOBILE ===================== --}}
        @hasrole('cmc')
        <x-ui.manifest-sidebar role="cmc" brand-sub="Placement & Careers" brand-icon="bi-briefcase-fill" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== DIRECTOR MOBILE ===================== --}}
        @hasrole('director')
        <x-ui.manifest-sidebar role="director" brand-sub="Institute Management" brand-icon="bi-person-badge" :show-brand="false" :show-footer="false" />
        @endhasrole

        {{-- ===================== ADMISSION MOBILE ===================== --}}
        @if($departmentHierarchyService->isAdmissionUser($user))
        <x-ui.manifest-sidebar role="admission" brand-sub="CRM & Enrollment" brand-icon="bi-person-plus-fill" :show-brand="false" :show-footer="false" />
        @endif

        @if($showDepartmentControls)
        <div class="sidebar-divider"></div>
        <div class="section-label">Department Controls</div>
        @if($showDepartmentHierarchyControl)
        <a href="{{ route('department-hierarchy.index') }}" class="nav-link @if(request()->routeIs('department-hierarchy.*')) active @endif"><i class="bi bi-person-workspace"></i> Department Hierarchy</a>
        @endif
        @if($showDepartmentGovernanceControl)
        <a href="{{ route('department-governance.index') }}" class="nav-link @if(request()->routeIs('department-governance.*')) active @endif"><i class="bi bi-sliders"></i> Department Governance</a>
        @endif
        @endif

    </div>
</div>

{{-- ===== MAIN CONTENT ===== --}}
<div class="main-content">
    @if(session('impersonation.original_user_id'))
        @php
            $impersonationActor = \App\Models\User::find(session('impersonation.original_user_id'));
        @endphp
        <div class="alert alert-warning d-flex justify-content-between align-items-center rounded-0 mb-0 px-4">
            <div>
                <i class="bi bi-person-bounding-box me-2"></i>
                You are impersonating this user{{ $impersonationActor ? ' as ' . $impersonationActor->name : '' }}.
            </div>
            <form method="POST" action="{{ route('department-governance.impersonation.stop') }}">
                @csrf
                <button class="btn btn-sm btn-outline-dark">Stop Impersonation</button>
            </form>
        </div>
    @endif

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
                <h1 class="h6 mb-0">@yield('page-title', trim($__env->yieldContent('title', 'Dashboard')))</h1>
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
            {{-- Global search - admin only --}}
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
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>View Profile</a></li>
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

