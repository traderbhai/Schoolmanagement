@extends('layouts.parent')
@section('title', 'Parent Dashboard')
@section('page-title', 'Dashboard')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="mb-4">
    <h5 class="fw-bold mb-0">Welcome, {{ auth()->user()->name }}</h5>
    <div class="text-muted" style="font-size:.85rem">Track attendance, fees, results, and notices for your linked students.</div>
</div>

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Parent monitoring sequence</div>
            <div class="small text-muted">Start with the priority card, then open each child record only when a fee, attendance, result, or notice needs attention.</div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Check parent priority</span>
            <span class="badge text-bg-light">2. Review child alerts</span>
            <span class="badge text-bg-light">3. Open attendance/results/fees</span>
            <span class="badge text-bg-light">4. Read notices</span>
            <span class="badge text-bg-light">5. Contact institute if blocked</span>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Parent Priority</div>
            <h5 class="fw-bold mb-1">{{ $parentPriority['title'] }}</h5>
            <p class="text-muted mb-0">{{ $parentPriority['body'] }}</p>
        </div>
        <a href="{{ $parentPriority['route'] }}" class="btn btn-primary btn-sm">
            <i class="bi bi-arrow-right-circle me-1"></i>{{ $parentPriority['action'] }}
        </a>
    </div>
</div>

@forelse($childrenData as $item)
@php
    $s = $item['student'];
    $finance = $item['finance'];
    $priority = $item['priority'];
@endphp
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="fw-semibold"><i class="bi bi-person-circle me-2 text-primary"></i>{{ $s->user->name }}</span>
        <div class="d-flex gap-2">
            <a href="{{ route('parent.children.attendance', $s) }}" class="btn btn-sm btn-outline-secondary">Attendance</a>
            <a href="{{ route('parent.children.results', $s) }}" class="btn btn-sm btn-outline-secondary">Results</a>
            <a href="{{ route('parent.children.fees', $s) }}" class="btn btn-sm btn-outline-secondary">Fees</a>
        </div>
    </div>
    <div class="card-body">
        <div class="alert {{ $priority['level'] === 'danger' ? 'alert-danger' : ($priority['level'] === 'warning' ? 'alert-warning' : 'alert-success') }} py-2 mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">{{ $priority['title'] }}</div>
                    <div style="font-size:.82rem">{{ $priority['body'] }}</div>
                </div>
                <a href="{{ $priority['route'] }}" class="btn btn-sm {{ $priority['level'] === 'danger' ? 'btn-danger' : ($priority['level'] === 'warning' ? 'btn-warning' : 'btn-outline-success') }}">
                    {{ $priority['action'] }}
                </a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-sm-4">
                <a href="{{ route('parent.children.attendance', $s) }}" class="kpi-card kpi-blue d-block text-decoration-none text-white" aria-label="Open attendance details for {{ $s->user->name }}">
                    <div class="kpi-label">Attendance</div>
                    <div class="kpi-value">{{ $item['attendancePct'] !== null ? $item['attendancePct'].'%' : 'N/A' }}</div>
                </a>
            </div>
            <div class="col-sm-4">
                <a href="{{ route('parent.children.results', $s) }}" class="kpi-card kpi-green d-block text-decoration-none text-white" aria-label="Open result details for {{ $s->user->name }}">
                    <div class="kpi-label">SGPA</div>
                    <div class="kpi-value">{{ $item['sgpa'] ?? 'N/A' }}</div>
                </a>
            </div>
            <div class="col-sm-4">
                <a href="{{ route('parent.children.fees', $s) }}" class="kpi-card {{ $finance['balance'] > 0 ? 'kpi-red' : 'kpi-green' }} d-block text-decoration-none text-white" aria-label="Open fee details for {{ $s->user->name }}">
                    <div class="kpi-label">Fee Balance</div>
                    <div class="kpi-value">Rs. {{ number_format($finance['balance']) }}</div>
                </a>
            </div>
        </div>

        <div class="mt-3 d-flex flex-wrap gap-3 text-muted" style="font-size:.8rem">
            <span><i class="bi bi-journal-bookmark me-1"></i>{{ optional($s->program)->name ?? optional($s->course)->name ?? 'N/A' }}</span>
            <span>Enrollment: {{ $s->enrollment_number }}</span>
            <span>Open demands: {{ $finance['open_demand_count'] }}</span>
            @if($finance['next_due_date'])
                <span>Next due: {{ $finance['next_due_date']->format('d M Y') }}</span>
            @endif
        </div>
    </div>
</div>
@empty
<div class="empty-state py-5 text-center">
    <div class="empty-icon"><i class="bi bi-people fs-1 text-muted"></i></div>
    <div class="mt-2 fw-semibold">No children linked to your account</div>
    <div class="text-muted">Please contact the administration.</div>
</div>
@endforelse

@if($notices->count())
<div class="card mt-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-megaphone me-2 text-primary"></i>Recent Notices</span>
        <a href="{{ route('parent.notices') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="list-group list-group-flush">
        @foreach($notices as $notice)
        <div class="list-group-item">
            <div class="fw-semibold" style="font-size:.9rem">{{ $notice->title }}</div>
            <div class="text-muted" style="font-size:.8rem">{{ $notice->publish_date->format('d M Y') }} &bull; {{ ucfirst($notice->audience) }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection
