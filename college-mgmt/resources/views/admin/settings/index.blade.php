@extends('layouts.admin')
@section('title','Settings')
@section('page-title','System Settings')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Settings</li>
@endsection
@section('content')
@include('admin.partials.setup-sequence')

<div class="row g-4">
    <div class="col-md-4">
        <div class="card h-100 border-0" style="box-shadow:var(--shadow-sm)">
            <div class="card-body text-center p-4">
                <div class="mb-3" style="width:60px;height:60px;background:var(--clr-primary-light,#eef2ff);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:1.8rem;color:var(--clr-primary)">
                    <i class="bi bi-palette"></i>
                </div>
                <h6 class="fw-bold mb-1">Branding</h6>
                <p class="text-muted small mb-3">Institute name, colors, contact info</p>
                <a href="{{ route('admin.settings.branding') }}" class="btn btn-primary btn-sm">Configure</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0" style="box-shadow:var(--shadow-sm)">
            <div class="card-body text-center p-4">
                <div class="mb-3" style="width:60px;height:60px;background:#f0fdf4;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:1.8rem;color:#059669">
                    <i class="bi bi-info-circle"></i>
                </div>
                <h6 class="fw-bold mb-1">System Info</h6>
                <p class="text-muted small mb-3">Runtime, framework, and database information</p>
                <div class="text-start small">
                    <div class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">PHP</span><span class="fw-semibold">{{ $phpVersion }}</span></div>
                    <div class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">App Framework</span><span class="fw-semibold">{{ $laravelVersion }}</span></div>
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Database</span><span class="fw-semibold">SQLite</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0" style="box-shadow:var(--shadow-sm)">
            <div class="card-body text-center p-4">
                <div class="mb-3" style="width:60px;height:60px;background:#fff7ed;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:1.8rem;color:#d97706">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h6 class="fw-bold mb-1">Activity Log</h6>
                <p class="text-muted small mb-3">System audit trail and user actions</p>
                <a href="{{ route('admin.activity-log') }}" class="btn btn-outline-secondary btn-sm">View Log</a>
            </div>
        </div>
    </div>
</div>
<div class="row g-4 mt-0">
    <div class="col-md-4">
        <div class="card h-100 border-0" style="box-shadow:var(--shadow-sm)">
            <div class="card-body text-center p-4">
                <div class="mb-3" style="width:60px;height:60px;background:#eff6ff;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:1.8rem;color:#2563eb">
                    <i class="bi bi-braces"></i>
                </div>
                <h6 class="fw-bold mb-1">REST API</h6>
                <p class="text-muted small mb-3">API reference, endpoints and authentication guide</p>
                <a href="{{ route('admin.api-docs') }}" class="btn btn-outline-primary btn-sm">View API Docs</a>
            </div>
        </div>
    </div>
</div>
@endsection
