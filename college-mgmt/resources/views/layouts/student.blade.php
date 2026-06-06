<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>@yield('title', 'EduManage — Student')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    @stack('styles')
</head>
<body>

{{-- ===== DESKTOP SIDEBAR ===== --}}
<div class="sidebar sidebar-desktop">
    <a class="sidebar-brand" href="{{ route('student.dashboard') }}">
        <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
        <span>
            <div class="brand-text">EduManage</div>
            <div class="brand-sub">Student Portal</div>
        </span>
    </a>

    <div class="mt-2 pb-4 flex-grow-1">
        <div class="section-label">Main</div>
        <a href="{{ route('student.dashboard') }}" class="nav-link @if(request()->routeIs('student.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Academics</div>
        <a href="{{ route('student.attendance') }}" class="nav-link @if(request()->routeIs('student.attendance')) active @endif">
            <i class="bi bi-check2-square"></i> My Attendance
        </a>
        <a href="{{ route('student.results') }}" class="nav-link @if(request()->routeIs('student.results')) active @endif">
            <i class="bi bi-award"></i> My Results
        </a>
        <a class="nav-link {{ request()->routeIs('student.subjects.*') ? 'active' : '' }}"
           href="{{ route('student.subjects.index') }}">
            <i class="bi bi-journal-text me-2"></i>Subject Registration
        </a>
        <a class="nav-link {{ request()->routeIs('student.timetable') ? 'active' : '' }}"
           href="{{ route('student.timetable') }}">
            <i class="bi bi-calendar3 me-2"></i>My Timetable
        </a>
        <a href="{{ route('student.transcript.download') }}"
           class="nav-link"
           title="Download official cumulative transcript">
            <i class="bi bi-file-earmark-text me-2"></i>Official Transcript
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Finance</div>
        <a href="{{ route('student.fees') }}" class="nav-link @if(request()->routeIs('student.fees')) active @endif">
            <i class="bi bi-cash-coin"></i> Fee Status
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Communication</div>
        <a href="{{ route('student.notices') }}" class="nav-link @if(request()->routeIs('student.notices*')) active @endif">
            <i class="bi bi-megaphone"></i> Notices
        </a>

        <div class="sidebar-divider"></div>

        <div class="sidebar-divider"></div>

        <div class="section-label">Career</div>
        <a href="{{ route('student.placements') }}" class="nav-link @if(request()->routeIs('student.placements*')) active @endif">
            <i class="bi bi-briefcase"></i> Placements
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Account</div>
        <a href="{{ route('student.profile') }}" class="nav-link @if(request()->routeIs('student.profile')) active @endif">
            <i class="bi bi-person-circle"></i> My Profile
        </a>
    </div>
</div>

{{-- ===== MOBILE OFFCANVAS SIDEBAR ===== --}}
<div class="offcanvas offcanvas-start sidebar-mobile" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" style="width:270px">
    <div class="offcanvas-header py-0" style="height:var(--topbar-height);background:rgba(0,0,0,.25);">
        <div class="d-flex align-items-center gap-2">
            <span style="width:30px;height:30px;background:var(--clr-primary);border-radius:7px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;"><i class="bi bi-mortarboard-fill"></i></span>
            <div>
                <div style="color:#f8fafc;font-weight:700;font-size:.9rem;line-height:1.2;" id="mobileSidebarLabel">EduManage</div>
                <div style="color:rgba(255,255,255,.45);font-size:.65rem;">Student Portal</div>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 pb-4">
        <div class="section-label">Main</div>
        <a href="{{ route('student.dashboard') }}" class="nav-link @if(request()->routeIs('student.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Academics</div>
        <a href="{{ route('student.attendance') }}" class="nav-link @if(request()->routeIs('student.attendance')) active @endif"><i class="bi bi-check2-square"></i> My Attendance</a>
        <a href="{{ route('student.results') }}" class="nav-link @if(request()->routeIs('student.results')) active @endif"><i class="bi bi-award"></i> My Results</a>
        <a class="nav-link {{ request()->routeIs('student.subjects.*') ? 'active' : '' }}"
           href="{{ route('student.subjects.index') }}"><i class="bi bi-journal-text me-2"></i>Subject Registration</a>
        <a class="nav-link {{ request()->routeIs('student.timetable') ? 'active' : '' }}"
           href="{{ route('student.timetable') }}"><i class="bi bi-calendar3 me-2"></i>My Timetable</a>
        <a href="{{ route('student.transcript.download') }}" class="nav-link">
            <i class="bi bi-file-earmark-text me-2"></i>Official Transcript</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Finance</div>
        <a href="{{ route('student.fees') }}" class="nav-link @if(request()->routeIs('student.fees')) active @endif"><i class="bi bi-cash-coin"></i> Fee Status</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Communication</div>
        <a href="{{ route('student.notices') }}" class="nav-link @if(request()->routeIs('student.notices*')) active @endif"><i class="bi bi-megaphone"></i> Notices</a>
        <div class="sidebar-divider"></div>
        <div class="sidebar-divider"></div>
        <div class="section-label">Career</div>
        <a href="{{ route('student.placements') }}" class="nav-link @if(request()->routeIs('student.placements*')) active @endif"><i class="bi bi-briefcase"></i> Placements</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Account</div>
        <a href="{{ route('student.profile') }}" class="nav-link @if(request()->routeIs('student.profile')) active @endif"><i class="bi bi-person-circle"></i> My Profile</a>
    </div>
</div>

{{-- ===== MAIN CONTENT ===== --}}
<div class="main-content">

    {{-- TOPBAR --}}
    <div class="topbar">
        <div class="topbar-left">
            <button class="btn btn-sm sidebar-mobile-toggle d-lg-none" type="button"
                    style="width:36px;height:36px;padding:0;border:1px solid var(--clr-border);border-radius:var(--radius-sm);background:transparent;color:var(--clr-text-muted);display:flex;align-items:center;justify-content:center;"
                    data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"
                    aria-controls="mobileSidebar" aria-label="Open navigation menu">
                <i class="bi bi-list fs-5"></i>
            </button>

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
            <div class="topbar-search d-none d-md-flex">
                <i class="bi bi-search search-icon"></i>
                <input type="search" placeholder="Search..." aria-label="Search">
            </div>

            <button class="theme-btn" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark mode">
                <i class="bi bi-moon-fill" id="themeIcon"></i>
            </button>

            <button class="notif-btn" aria-label="Notifications" title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="notif-badge"></span>
            </button>

            <div class="dropdown">
                <button class="user-avatar dropdown-toggle" style="border:none;" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User menu">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:200px;font-size:.84rem;border-color:var(--clr-border);">
                    <li>
                        <div class="px-3 py-2">
                            <div style="font-weight:600;color:var(--clr-text);">{{ auth()->user()->name }}</div>
                            <div style="font-size:.75rem;color:var(--clr-text-muted);">{{ auth()->user()->email }}</div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item" href="{{ route('student.profile') }}"><i class="bi bi-person me-2"></i>View Profile</a></li>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ── Auto-dismiss success/info alerts ──────────────────────
(function () {
    setTimeout(function () {
        document.querySelectorAll('.js-auto-dismiss').forEach(function (el) {
            var alert = bootstrap.Alert.getOrCreateInstance(el);
            alert.close();
        });
    }, 4000);
})();

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
</script>

@stack('scripts')
</body>
</html>
