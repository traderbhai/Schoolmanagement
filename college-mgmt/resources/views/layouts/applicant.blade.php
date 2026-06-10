<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EduManage — Applicant Portal')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    @stack('styles')
</head>
<body>

{{-- ===== DESKTOP SIDEBAR ===== --}}
<div class="sidebar sidebar-desktop">
    <a class="sidebar-brand" href="{{ route('applicant.dashboard') }}">
        <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
        <span>
            <div class="brand-text">EduManage</div>
            <div class="brand-sub">Applicant Portal</div>
        </span>
    </a>

    <div class="mt-2 pb-4 flex-grow-1">
        <div class="section-label">Main</div>
        <a href="{{ route('applicant.dashboard') }}" class="nav-link @if(request()->routeIs('applicant.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Application</div>
        <a href="{{ route('applicant.application.show') }}" class="nav-link @if(request()->routeIs('applicant.application.*')) active @endif">
            <i class="bi bi-file-earmark-text"></i> My Application
        </a>
        <a href="{{ route('applicant.documents.index') }}" class="nav-link @if(request()->routeIs('applicant.documents')) active @endif">
            <i class="bi bi-folder2-open"></i> Documents
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Track</div>
        <a href="{{ route('applicant.status') }}" class="nav-link @if(request()->routeIs('applicant.status')) active @endif">
            <i class="bi bi-clipboard2-check"></i> Status Tracker
        </a>
        <a href="{{ route('applicant.fees.index') }}" class="nav-link @if(request()->routeIs('applicant.fees.*')) active @endif">
            <i class="bi bi-cash-coin"></i> Fees & Payments
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Account</div>
        <a href="{{ route('profile.edit') }}" class="nav-link">
            <i class="bi bi-gear"></i> Account Settings
        </a>
    </div>

    <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                <i class="bi bi-box-arrow-left"></i> Logout
            </button>
        </form>
    </div>
</div>

{{-- ===== TOPBAR ===== --}}
<div class="topbar">
    <button class="topbar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
        <i class="bi bi-list fs-4"></i>
    </button>
    <span class="topbar-title d-none d-lg-block">@yield('page-title', 'Applicant Portal')</span>
    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="text-muted small"><i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }}</span>
    </div>
</div>

{{-- ===== MOBILE OFFCANVAS SIDEBAR ===== --}}
<div class="offcanvas offcanvas-start sidebar" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header border-bottom">
        <div class="sidebar-brand w-100">
            <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
            <span>
                <div class="brand-text">EduManage</div>
                <div class="brand-sub">Applicant Portal</div>
            </span>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="mt-2 pb-4">
            <a href="{{ route('applicant.dashboard') }}" class="nav-link @if(request()->routeIs('applicant.dashboard')) active @endif">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="{{ route('applicant.application.show') }}" class="nav-link @if(request()->routeIs('applicant.application.*')) active @endif">
                <i class="bi bi-file-earmark-text"></i> My Application
            </a>
            <a href="{{ route('applicant.documents.index') }}" class="nav-link @if(request()->routeIs('applicant.documents')) active @endif">
                <i class="bi bi-folder2-open"></i> Documents
            </a>
            <a href="{{ route('applicant.status') }}" class="nav-link @if(request()->routeIs('applicant.status')) active @endif">
                <i class="bi bi-clipboard2-check"></i> Status Tracker
            </a>
            <a href="{{ route('applicant.fees.index') }}" class="nav-link @if(request()->routeIs('applicant.fees.*')) active @endif">
                <i class="bi bi-cash-coin"></i> Fees & Payments
            </a>
        </div>
    </div>
</div>

{{-- ===== MAIN CONTENT ===== --}}
<main class="main-content">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mx-3 mt-3" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Please fix the errors below:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
