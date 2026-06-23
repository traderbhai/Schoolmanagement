@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div><h1 class="h4 mb-1">{{ $title }}</h1><div class="small text-muted">{{ $description }}</div></div>
        @include('academics.pmc.v041.partials.nav')
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['Published Sessions', $dashboard['published_sessions'], 'primary'],
            ['Bridge Failures', $dashboard['bridge_failures'], $dashboard['bridge_failures'] ? 'danger' : 'success'],
            ['Unacknowledged Faculty', $dashboard['unacknowledged_faculty'], $dashboard['unacknowledged_faculty'] ? 'warning' : 'success'],
            ['Uncovered Substitutions', $dashboard['uncovered_substitutions'], $dashboard['uncovered_substitutions'] ? 'danger' : 'success'],
            ['Room Issues', $dashboard['room_readiness_issues'], $dashboard['room_readiness_issues'] ? 'warning' : 'success'],
            ['Same-Day Changes', $dashboard['same_day_changes'], $dashboard['same_day_changes'] ? 'warning' : 'success'],
            ['Frequent Changes', $dashboard['frequent_change_sessions'], $dashboard['frequent_change_sessions'] ? 'warning' : 'success'],
            ['Missing Attendance', $dashboard['sessions_missing_attendance'], $dashboard['sessions_missing_attendance'] ? 'warning' : 'success'],
        ] as [$label, $value, $color])
            <div class="col-6 col-md-3 col-xl"><div class="card shadow-sm h-100"><div class="card-body py-2"><div class="small text-muted">{{ $label }}</div><div class="h4 mb-0 text-{{ $color }}">{{ $value }}</div></div></div></div>
        @endforeach
    </div>

    <div class="card shadow-sm">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between gap-2">
            <div><div class="fw-semibold">Operational Readiness Snapshot</div><div class="small text-muted">The same prerequisites remain visible after publish so revision work starts from the canonical source.</div></div>
            <span class="badge text-bg-{{ $readiness['status'] === 'ready' ? 'success' : ($readiness['status'] === 'warning' ? 'warning' : 'danger') }}">{{ $readiness['status'] }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Area</th><th>Status</th><th>Message</th></tr></thead>
                <tbody>
                    @foreach($readiness['checks'] as $check)
                        <tr><td>{{ $check['label'] }}</td><td>{{ $check['status'] }}</td><td class="small">{{ $check['message'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
