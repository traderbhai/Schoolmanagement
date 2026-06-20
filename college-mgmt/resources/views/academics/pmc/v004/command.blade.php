@extends('layouts.admin')
@section('title', 'PMC Command OS')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div><h1 class="h4 mb-1">PMC Command OS</h1><div class="small text-muted">Complete daily control room for planning, curriculum, faculty, timetable, delivery, students, approvals, automation, and reports. Scope: {{ $scopeLabel }}</div></div>
        @include('academics.pmc.v004.partials.nav')
    </div>
    <div class="row g-2 mb-3">
        @foreach([
            ['Semester Readiness', $kpis['semester_readiness'] . '%', $links['semester_readiness']],
            ['Curriculum Blockers', $kpis['curriculum_blockers'], $links['curriculum_blockers']],
            ['Unassigned Subjects', $kpis['unassigned_subjects'], $links['unassigned_subjects']],
            ['Faculty Overload', $kpis['faculty_overload'], $links['faculty_overload']],
            ['Timetable Conflicts', $kpis['timetable_conflicts'], $links['timetable_conflicts']],
            ['Delivery Delay', $kpis['course_delivery_delay'], $links['course_delivery_delay']],
            ['Marks Pending', $kpis['marks_pending'], $links['marks_pending']],
            ['Student Risk', $kpis['student_success_risk'], $links['student_success_risk']],
            ['Overdue Actions', $kpis['overdue_actions'], $links['overdue_actions']],
        ] as [$label,$value,$url])
            <div class="col-6 col-md-4 col-xl"><a href="{{ $url }}" class="card text-decoration-none text-reset shadow-sm h-100"><div class="card-body py-2"><div class="d-flex justify-content-between gap-2"><div class="small text-muted">{{ $label }}</div><i class="bi bi-arrow-up-right small text-muted"></i></div><div class="h4 mb-0">{{ $value }}</div></div></a></div>
        @endforeach
    </div>
    <div class="row g-3">
        <div class="col-xl-7"><div class="card shadow-sm h-100"><div class="card-header py-2 d-flex justify-content-between"><span class="fw-semibold">Immediate Attention</span><a href="{{ route('academics.pmc.attention.index') }}" class="btn btn-sm btn-outline-primary">Open Queue</a></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Issue</th><th>Owner</th><th>Severity</th><th>Action</th></tr></thead><tbody>@foreach($attention as $item)<tr><td><div class="fw-semibold">{{ $item['title'] }}</div><div class="small text-muted">{{ $item['subtitle'] }} | Due {{ $item['due'] ?: 'not set' }}</div></td><td>{{ $item['owner'] }}</td><td><span class="badge text-bg-{{ $item['severity'] === 'critical' ? 'danger' : 'warning' }}">{{ $item['severity'] }}</span></td><td><a href="{{ $item['route'] }}" class="btn btn-sm btn-outline-secondary">{{ $item['action'] }}</a></td></tr>@endforeach</tbody></table></div></div></div>
        <div class="col-xl-5"><div class="card shadow-sm h-100"><div class="card-header py-2 fw-semibold">Pending Approval Cockpit</div><div class="list-group list-group-flush">@foreach($approvals as $approval)<a class="list-group-item list-group-item-action py-2" href="{{ route('academics.pmc.approvals.index', ['status' => $approval->status]) }}"><div class="fw-semibold">{{ $approval->title }}</div><div class="small text-muted">{{ str($approval->approval_type)->headline() }} | {{ $approval->sla_status }} | {{ optional($approval->due_at)->format('d M') }}</div></a>@endforeach</div></div></div>
    </div>
    <div class="card shadow-sm mt-3"><div class="card-header py-2 d-flex justify-content-between"><span class="fw-semibold">PMC Reports</span><a href="{{ route('academics.pmc.analytics.index') }}" class="small">Analytics</a></div><div class="row g-0">@foreach($reports as $report)<div class="col-md-3 col-xl border-end"><a href="{{ $report['route'] }}" class="d-block p-2 text-decoration-none"><div class="small text-muted">{{ $report['label'] }}</div><div class="fw-semibold">{{ $report['count'] }}</div></a></div>@endforeach</div></div>
</div>
@endsection
