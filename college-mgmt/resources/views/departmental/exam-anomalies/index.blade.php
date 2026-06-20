@extends('layouts.admin')
@section('title', 'Exam Anomaly Log')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Exam Anomaly Log</h1>
            <span class="text-muted small">Track malpractice, late entries, and other exam irregularities</span>
        </div>
        <a href="{{ route('exam-cell.anomalies.create') }}" class="btn btn-danger btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Report Anomaly
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <form method="GET" class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label small fw-semibold text-muted">Severity</label>
                    <select name="severity" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(['critical','high','medium','low'] as $s)
                            <option value="{{ $s }}" @selected(request('severity')===$s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3">
                    <label class="form-label small fw-semibold text-muted">Type</label>
                    <select name="anomaly_type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        @foreach(['malpractice','absent_without_reason','late_entry','paper_leak','other'] as $t)
                            <option value="{{ $t }}" @selected(request('anomaly_type')===$t)>{{ ucwords(str_replace('_',' ',$t)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3">
                    <button type="submit" class="btn btn-outline-primary btn-sm me-1">Filter</button>
                    <a href="{{ route('exam-cell.anomalies.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Exam</th>
                        <th>Student</th>
                        <th>Type</th>
                        <th>Severity</th>
                        <th>Action Taken</th>
                        <th>Reported By</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    @php
                        $sev = ['critical'=>'danger','high'=>'warning','medium'=>'info','low'=>'secondary'][$log->severity] ?? 'secondary';
                    @endphp
                    <tr>
                        <td class="small">{{ $log->exam->subject->name ?? $log->exam->name ?? '—' }}</td>
                        <td class="fw-semibold small">{{ $log->student->user->name ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark">{{ ucwords(str_replace('_',' ',$log->anomaly_type)) }}</span></td>
                        <td><span class="badge bg-{{ $sev }}">{{ ucfirst($log->severity) }}</span></td>
                        <td class="small text-muted">{{ $log->action_taken ? ucfirst($log->action_taken) : '—' }}</td>
                        <td class="small text-muted">{{ $log->reportedBy->name ?? '—' }}</td>
                        <td class="small text-muted">{{ $log->created_at->format('d M Y') }}</td>
                        <td><a href="{{ route('exam-cell.anomalies.show', $log) }}" class="btn btn-outline-primary btn-sm">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No anomalies logged.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="card-footer bg-transparent">{{ $logs->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
