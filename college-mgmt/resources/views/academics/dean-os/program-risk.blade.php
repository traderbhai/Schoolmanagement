@extends('layouts.admin')
@section('title', 'Dean Program Risk')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Program Risk Heatmap</h1><div class="small text-muted">Deterministic risk scoring across attendance, performance, delivery, exams, quality, handoff, and actions.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="card shadow-sm mb-3"><div class="card-body py-2 small text-muted">Visible filter summary: all active programs | <a href="{{ route('academics.dean-os.export', 'program_risk') }}">Export Current View</a></div></div>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Program</th><th>Score</th><th>Attendance</th><th>Performance</th><th>Delivery</th><th>Exams</th><th>Quality</th><th>Handoff</th><th>Reasons</th></tr></thead>
                <tbody>
                @foreach($risks as $risk)
                    <tr>
                        <td><div class="fw-semibold">{{ $risk['program']->name }}</div><div class="small text-muted">{{ $risk['program']->code }}</div></td>
                        <td><span class="badge text-bg-{{ $risk['band'] === 'critical' ? 'danger' : ($risk['band'] === 'high' ? 'warning' : 'light') }}">{{ $risk['band'] }} {{ $risk['score'] }}</span></td>
                        <td>{{ $risk['metrics']['attendanceExceptions'] }}</td><td>{{ $risk['metrics']['failedResults'] }}</td><td>{{ $risk['metrics']['facultyGaps'] + $risk['metrics']['draftTimetable'] }}</td><td>{{ $risk['metrics']['examBlocks'] }}</td><td>{{ $risk['metrics']['feedbackGaps'] }}</td><td>{{ $risk['metrics']['handoffBlocks'] }}</td>
                        <td class="small text-muted">{{ $risk['reasons']->join(', ') ?: 'No major risk signals' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
