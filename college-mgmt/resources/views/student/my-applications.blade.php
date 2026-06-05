@extends('layouts.student')
@section('title', 'My Applications')
@section('page-title', 'My Applications')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('student.placements') }}">Placements</a></li>
    <li class="breadcrumb-item active">My Applications</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header py-3">
        <h6 class="mb-0 fw-bold">My Placement Applications</h6>
    </div>
    <div class="card-body p-0">
        @if($myApplications->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No applications yet. <a href="{{ route('student.placements') }}">Browse open drives</a>.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Drive / Company</th>
                            <th>Job Role</th>
                            <th>Package</th>
                            <th>Status</th>
                            <th>Applied On</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($myApplications as $app)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $app->drive->title }}</div>
                                <div class="text-muted" style="font-size:.78rem">{{ $app->drive->company->name }}</div>
                            </td>
                            <td>{{ $app->drive->job_role }}</td>
                            <td>{{ $app->drive->package ?? '—' }}</td>
                            <td>
                                @php
                                    $statusBadge = [
                                        'applied'     => 'bg-info',
                                        'shortlisted' => 'bg-primary',
                                        'interview'   => 'bg-warning text-dark',
                                        'selected'    => 'bg-success',
                                        'rejected'    => 'bg-danger',
                                        'withdrawn'   => 'bg-secondary',
                                    ];
                                @endphp
                                <span class="badge {{ $statusBadge[$app->application_status] ?? 'bg-secondary' }}">
                                    {{ ucfirst($app->application_status) }}
                                </span>
                            </td>
                            <td>{{ $app->created_at->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
