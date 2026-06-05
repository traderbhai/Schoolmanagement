@extends('layouts.parent')
@section('title', 'Parent Dashboard')
@section('page-title', 'Dashboard')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="mb-4">
    <h5 class="fw-bold mb-0">Welcome, {{ auth()->user()->name }}</h5>
    <div class="text-muted" style="font-size:.85rem">Here's an overview of your children's progress.</div>
</div>

{{-- Children KPI cards --}}
@forelse($childrenData as $item)
@php $s = $item['student']; @endphp
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-person-circle me-2 text-primary"></i>{{ $s->user->name }}</span>
        <div class="d-flex gap-2">
            <a href="{{ route('parent.children.attendance', $s) }}" class="btn btn-sm btn-outline-secondary">Attendance</a>
            <a href="{{ route('parent.children.results', $s) }}" class="btn btn-sm btn-outline-secondary">Results</a>
            <a href="{{ route('parent.children.fees', $s) }}" class="btn btn-sm btn-outline-secondary">Fees</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-4">
                <div class="kpi-card kpi-blue">
                    <div class="kpi-label">Attendance</div>
                    <div class="kpi-value">{{ $item['attendancePct'] !== null ? $item['attendancePct'].'%' : 'N/A' }}</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="kpi-card kpi-green">
                    <div class="kpi-label">SGPA</div>
                    <div class="kpi-value">{{ $item['sgpa'] ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="kpi-card {{ $item['balance'] > 0 ? 'kpi-red' : 'kpi-green' }}">
                    <div class="kpi-label">Fee Balance</div>
                    <div class="kpi-value">₹{{ number_format($item['balance']) }}</div>
                </div>
            </div>
        </div>
        <div class="mt-2 text-muted" style="font-size:.8rem">
            <i class="bi bi-journal-bookmark me-1"></i>{{ optional($s->course)->name ?? 'N/A' }}
            &bull; Enrollment: {{ $s->enrollment_number }}
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

{{-- Recent Notices --}}
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
