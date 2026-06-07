@extends('layouts.student')
@section('title', $subject->name . ' — Session Attendance')

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.attendance') }}">Attendance</a></li>
            <li class="breadcrumb-item active">{{ $subject->name }}</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-semibold mb-0">{{ $subject->name }} — Session-wise Attendance</h4>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-3 fw-bold">{{ $total }}</div>
                    <div class="text-muted small">Total Sessions</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-3 fw-bold text-success">{{ $present }}</div>
                    <div class="text-muted small">Present</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-3 fw-bold text-danger">{{ $total - $present }}</div>
                    <div class="text-muted small">Absent</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-3 fw-bold {{ $pct < 75 ? 'text-danger' : 'text-success' }}">{{ $pct }}%</div>
                    <div class="text-muted small">Attendance %</div>
                </div>
            </div>
        </div>
    </div>

    @if($pct < 75)
    <div class="alert alert-danger mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        Your attendance is below 75%. You need to attend {{ ceil(($total * 0.75 - $present) / 0.25) }} more sessions to reach the minimum requirement.
    </div>
    @endif

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Time Slot</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sessions as $session)
                <tr>
                    <td>{{ $session->date->format('d M Y') }}</td>
                    <td class="text-muted">{{ $session->date->format('l') }}</td>
                    <td>{{ $session->timetableEntry->slot->name ?? ($session->timetableEntry->slot->start_time ?? '—') }}</td>
                    <td>
                        @if($session->status === 'present')
                            <span class="badge bg-success">Present</span>
                        @elseif($session->status === 'late')
                            <span class="badge bg-warning text-dark">Late</span>
                        @elseif($session->status === 'excused')
                            <span class="badge bg-info">Excused</span>
                        @else
                            <span class="badge bg-danger">Absent</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $sessions->links() }}
</div>
@endsection
