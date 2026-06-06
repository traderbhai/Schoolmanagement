@extends('layouts.admin')
@section('title', 'Institutional Reports')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Institutional Reports</h4>
            <span class="text-muted small">High-level KPIs for {{ now()->year }}</span>
        </div>
        <a href="{{ route('director.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">Placement Summary</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6 text-muted">Total Students</dt>
                        <dd class="col-6 fw-bold">{{ $totalStudents }}</dd>

                        <dt class="col-6 text-muted">Placement Rate ({{ now()->year }})</dt>
                        <dd class="col-6 fw-bold text-success">{{ $placementRate }}%</dd>
                    </dl>

                    <div class="progress mt-3" style="height:8px">
                        <div class="progress-bar bg-success" style="width:{{ $placementRate }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">Available Reports</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('accounts.reports') }}" class="btn btn-outline-secondary text-start btn-sm">
                            <i class="bi bi-cash-stack me-2"></i>Fee & Revenue Reports
                        </a>
                        <a href="{{ route('cmc.analytics') }}" class="btn btn-outline-secondary text-start btn-sm">
                            <i class="bi bi-briefcase me-2"></i>Placement Analytics
                        </a>
                        <a href="{{ route('academic.attendance.report') }}" class="btn btn-outline-secondary text-start btn-sm">
                            <i class="bi bi-calendar-check me-2"></i>Attendance Reports
                        </a>
                        <a href="{{ route('exam-cell.results') }}" class="btn btn-outline-secondary text-start btn-sm">
                            <i class="bi bi-journal-text me-2"></i>Exam Results
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
