<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EduManage - Parent')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    @stack('styles')
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>

{{-- ===== DESKTOP SIDEBAR ===== --}}
<div class="sidebar sidebar-desktop">
    <x-ui.manifest-sidebar role="parent" brand-sub="Parent Portal" brand-icon="bi-house-heart-fill" :show-footer="false" />
</div>

{{-- ===== MOBILE OFFCANVAS SIDEBAR ===== --}}
<div class="offcanvas offcanvas-start sidebar-mobile" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" style="width:270px">
    <div class="offcanvas-header py-0" style="height:var(--topbar-height);background:rgba(0,0,0,.25);">
        <div class="d-flex align-items-center gap-2">
            <span style="width:30px;height:30px;background:var(--clr-primary);border-radius:7px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;"><i class="bi bi-house-heart-fill"></i></span>
            <div>
                <div style="color:#f8fafc;font-weight:700;font-size:.9rem;line-height:1.2;" id="mobileSidebarLabel">EduManage</div>
                <div style="color:rgba(255,255,255,.45);font-size:.65rem;">Parent Portal</div>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close navigation menu"></button>
    </div>
    <div class="offcanvas-body p-0 pb-4">
        <x-ui.manifest-sidebar
            role="parent"
            brand-sub="Parent Portal"
            brand-icon="bi-house-heart-fill"
            :show-brand="false"
            :show-footer="false"
        />
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
            <button class="theme-btn" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark mode">
                <i class="bi bi-moon-fill" id="themeIcon"></i>
            </button>

            <div class="dropdown">
                <button type="button" class="user-avatar dropdown-toggle" style="border:none;" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User menu">
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
    <main id="main-content" class="page-body" tabindex="-1">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show js-auto-dismiss" role="alert">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif

        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
