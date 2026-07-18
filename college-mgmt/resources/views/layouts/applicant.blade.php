<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EduManage - Applicant Portal')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    @stack('styles')
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>

{{-- ===== DESKTOP SIDEBAR ===== --}}
<div class="sidebar sidebar-desktop">
    <x-ui.manifest-sidebar role="applicant" brand-sub="Applicant Portal" brand-icon="bi-mortarboard-fill" />
</div>

{{-- ===== TOPBAR ===== --}}
<div class="topbar">
    <button class="topbar-toggle sidebar-mobile-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open navigation menu">
        <i class="bi bi-list fs-4"></i>
    </button>
    <h1 class="topbar-title d-none d-lg-block mb-0">@yield('page-title', 'Applicant Portal')</h1>
    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="text-muted small"><i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }}</span>
    </div>
</div>

{{-- ===== MOBILE OFFCANVAS SIDEBAR ===== --}}
<div class="offcanvas offcanvas-start sidebar-mobile" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header border-bottom">
        <div class="sidebar-brand w-100">
            <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
            <span>
                <div class="brand-text">EduManage</div>
                <div class="brand-sub">Applicant Portal</div>
            </span>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas" aria-label="Close navigation menu"></button>
    </div>
    <div class="offcanvas-body p-0">
        <x-ui.manifest-sidebar
            role="applicant"
            brand-sub="Applicant Portal"
            brand-icon="bi-mortarboard-fill"
            :show-brand="false"
            :show-footer="false"
        />
    </div>
</div>

{{-- ===== MAIN CONTENT ===== --}}
<main id="main-content" class="main-content" tabindex="-1">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mx-3 mt-3" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
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
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
        </div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
