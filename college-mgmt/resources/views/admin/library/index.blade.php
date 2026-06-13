@extends('layouts.admin')
@section('title', 'Library Management')
@section('page-title', 'Library Management')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Library</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-4 col-md-2">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-book"></i></div>
            <div class="kpi-body"><div class="kpi-value">{{ $totalBooks }}</div><div class="kpi-label">Total Books</div></div>
        </div>
    </div>
    <div class="col-sm-4 col-md-2">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-collection"></i></div>
            <div class="kpi-body"><div class="kpi-value">{{ $totalCopies }}</div><div class="kpi-label">Total Copies</div></div>
        </div>
    </div>
    <div class="col-sm-4 col-md-2">
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon"><i class="bi bi-arrow-up-right-square"></i></div>
            <div class="kpi-body"><div class="kpi-value">{{ $issuedToday }}</div><div class="kpi-label">Issued Today</div></div>
        </div>
    </div>
    <div class="col-sm-4 col-md-2">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="kpi-body"><div class="kpi-value">{{ $overdueCount }}</div><div class="kpi-label">Overdue</div></div>
        </div>
    </div>
    <div class="col-sm-4 col-md-2">
        <div class="kpi-card kpi-yellow">
            <div class="kpi-icon"><i class="bi bi-calendar-check"></i></div>
            <div class="kpi-body"><div class="kpi-value">{{ $dueToday }}</div><div class="kpi-label">Due Today</div></div>
        </div>
    </div>
    <div class="col-sm-4 col-md-2">
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-currency-rupee"></i></div>
            <div class="kpi-body"><div class="kpi-value">{{ $finesPending }}</div><div class="kpi-label">Fines Pending</div></div>
        </div>
    </div>
</div>

{{-- Quick Links --}}
<div class="row g-3 mb-4">
    <div class="col-auto">
        <a href="{{ route('admin.library.books') }}" class="btn btn-primary"><i class="bi bi-book me-1"></i>Books Catalog</a>
    </div>
    <div class="col-auto">
        <a href="{{ route('admin.library.issues') }}" class="btn btn-outline-primary"><i class="bi bi-list-check me-1"></i>All Issues</a>
    </div>
    <div class="col-auto">
        <a href="{{ route('admin.library.issues') }}?status=overdue" class="btn btn-outline-danger"><i class="bi bi-exclamation-circle me-1"></i>Overdue Issues</a>
    </div>
    <div class="col-auto">
        <a href="{{ route('admin.library.memberships') }}" class="btn btn-outline-secondary"><i class="bi bi-person-badge me-1"></i>Memberships</a>
    </div>
    <div class="col-auto">
        <a href="{{ route('admin.library.fines') }}" class="btn btn-outline-warning"><i class="bi bi-currency-rupee me-1"></i>Fines</a>
    </div>
</div>

{{-- Recent Issues Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Issues</h6>
        <a href="{{ route('admin.library.issues') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Book</th>
                        <th>Accession</th>
                        <th>Borrower</th>
                        <th>Issued At</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($latestIssues as $issue)
                    <tr>
                        <td>{{ $issue->bookCopy->book->title ?? 'N/A' }}</td>
                        <td><code>{{ $issue->bookCopy->accession_number ?? '' }}</code></td>
                        <td>
                            @if($issue->student)
                                <span class="badge bg-primary">Student</span> {{ $issue->student->user->name ?? '' }}
                            @elseif($issue->teacher)
                                <span class="badge bg-success">Teacher</span> {{ $issue->teacher->user->name ?? '' }}
                            @else N/A
                            @endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($issue->issued_at)->format('d M Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($issue->due_date)->format('d M Y') }}</td>
                        <td>
                            @if($issue->status === 'returned')
                                <span class="badge bg-success">Returned</span>
                            @elseif($issue->status === 'overdue')
                                <span class="badge bg-danger">Overdue</span>
                            @elseif($issue->status === 'issued')
                                <span class="badge bg-primary">Issued</span>
                            @else
                                <span class="badge bg-secondary">{{ $issue->status }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No issues yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
