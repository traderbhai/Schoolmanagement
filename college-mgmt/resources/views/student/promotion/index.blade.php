@extends('layouts.student')
@section('title', 'Term Promotion Status')
@section('page-title', 'Term Promotion Status')

@section('content')
<div class="container py-4" style="max-width:800px">

    {{-- Current status banner --}}
    @if($latest)
    @php
        $bannerClass = match($latest->status) {
            'approved'  => 'success',
            'rejected'  => 'danger',
            'deferred'  => 'warning',
            default     => 'info',
        };
        $bannerIcon = match($latest->status) {
            'approved' => 'check-circle-fill',
            'rejected' => 'x-circle-fill',
            default    => 'info-circle-fill',
        };
    @endphp
    <div class="alert alert-{{ $bannerClass }} d-flex align-items-center mb-4">
        <i class="bi bi-{{ $bannerIcon }} fs-4 me-3 flex-shrink-0"></i>
        <div>
            <strong>
                @if($latest->status === 'approved') Promoted to {{ $latest->promotedToTerm->name ?? 'next term' }}
                @elseif($latest->status === 'rejected') Promotion Withheld
                @else Promotion status: {{ ucfirst($latest->status) }}
                @endif
            </strong>
            @if($latest->remarks)
            <div class="small mt-1">{{ $latest->remarks }}</div>
            @endif
            @if($latest->processed_at)
            <div class="small text-muted mt-1">Processed on {{ $latest->processed_at->format('d M Y') }}</div>
            @endif
        </div>
    </div>
    @else
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>No promotion record found yet. Promotion is processed at the end of each term.
    </div>
    @endif

    {{-- Criteria breakdown (latest) --}}
    @if($latest)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-clipboard-data me-2"></i>Eligibility Criteria</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Criterion</th><th>Value</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Academic Performance (CGPA)</td>
                        <td>{{ $latest->cgpa ?? '—' }}</td>
                        <td>
                            @if($latest->meets_academic_criteria)
                                <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Met</span>
                            @else
                                <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>Not Met</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>Attendance</td>
                        <td>{{ $latest->attendance_percentage ? $latest->attendance_percentage . '%' : '—' }}</td>
                        <td>
                            @if($latest->meets_attendance_criteria)
                                <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Met</span>
                            @else
                                <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>Not Met</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Full history --}}
    @if($promotions->count() > 1)
    <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2"></i>Promotion History</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>From Term</th><th>To Term</th><th>CGPA</th><th>Attendance</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    @foreach($promotions as $p)
                    @php $badge = match($p->status) { 'approved'=>'success','rejected'=>'danger','deferred'=>'warning',default=>'secondary' }; @endphp
                    <tr>
                        <td>{{ $p->currentTerm->name ?? '—' }}</td>
                        <td>{{ $p->promotedToTerm->name ?? '—' }}</td>
                        <td>{{ $p->cgpa ?? '—' }}</td>
                        <td>{{ $p->attendance_percentage ? $p->attendance_percentage . '%' : '—' }}</td>
                        <td><span class="badge bg-{{ $badge }}">{{ ucfirst($p->status) }}</span></td>
                        <td class="text-muted small">{{ $p->processed_at?->format('d M Y') ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
