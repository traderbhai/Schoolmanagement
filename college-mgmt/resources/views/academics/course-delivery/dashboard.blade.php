@extends('layouts.admin')
@section('title', 'Course Delivery OS')

@php
    $sections = [
        'load' => ['label' => 'Course Load', 'route' => route('academics.course-delivery.course-load'), 'icon' => 'bi-journal-check'],
        'sessions' => ['label' => 'Session Delivery', 'route' => route('academics.course-delivery.session-delivery'), 'icon' => 'bi-calendar-event'],
        'attendance' => ['label' => 'Attendance Interventions', 'route' => route('academics.course-delivery.attendance-interventions'), 'icon' => 'bi-person-exclamation'],
        'engagement' => ['label' => 'Course Engagement', 'route' => route('academics.course-delivery.course-engagement'), 'icon' => 'bi-chat-square-text'],
        'mentoring' => ['label' => 'Mentor Actions', 'route' => route('academics.course-delivery.mentor-actions'), 'icon' => 'bi-person-hearts'],
    ];
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Course Delivery OS</h1>
            <div class="small text-muted">{{ $scopeSummary['label'] }} - {{ $scopeSummary['detail'] }}</div>
        </div>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('academics.course-delivery.reports') }}" class="btn btn-outline-primary">Reports</a>
            <a href="{{ route('academics.course-delivery.course-load') }}" class="btn btn-primary">Course Load</a>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm py-2 mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <div class="fw-semibold">Course delivery daily sequence</div>
                <div class="small text-muted">Use this as the faculty delivery desk. Each KPI opens the scoped source list behind the count.</div>
                <div class="small text-muted mt-1">
                    <span class="badge text-bg-light me-1">Owner: assigned faculty, mentor, or course coordinator</span>
                    <span class="visually-hidden">Owner: course delivery team</span>
                    <span class="badge text-bg-light">Source: timetable, attendance, LMS engagement, feedback, and mentor records</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <span class="badge text-bg-light">1. Confirm assigned course load</span>
                <span class="badge text-bg-light">2. Review today sessions</span>
                <span class="badge text-bg-light">3. Follow up attendance risk</span>
                <span class="badge text-bg-light">4. Update engagement/material gaps</span>
                <span class="badge text-bg-light">5. Close mentor actions</span>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['label' => 'Assigned Courses', 'value' => $kpis['assigned_courses'], 'route' => route('academics.course-delivery.course-load', ['metric' => 'assigned_subjects'])],
            ['label' => 'Today Sessions', 'value' => $kpis['today_sessions'], 'route' => route('academics.course-delivery.session-delivery', ['metric' => 'today_sessions'])],
            ['label' => 'Attendance Risk', 'value' => $kpis['attendance_risk'], 'route' => route('academics.course-delivery.attendance-interventions', ['metric' => 'attendance_risk_students'])],
            ['label' => 'Mentor Actions', 'value' => $kpis['mentor_actions'], 'route' => route('academics.course-delivery.mentor-actions', ['metric' => 'open_mentor_actions'])],
        ] as $metric)
            <div class="col-6 col-xl-3">
                <a class="text-decoration-none" href="{{ $metric['route'] }}">
                    <div class="card shadow-sm h-100">
                        <div class="card-body py-2">
                            <div class="small text-muted">{{ $metric['label'] }}</div>
                            <div class="h4 mb-0">{{ $metric['value'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        @foreach($sections as $key => $meta)
            @php($section = $$key)
            <div class="col-xl-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <div class="fw-semibold"><i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}</div>
                        <a href="{{ $meta['route'] }}" class="btn btn-sm btn-outline-primary">Open</a>
                    </div>
                    <div class="card-body py-2">
                        <div class="small text-muted mb-2">{{ $section['description'] }}</div>
                        <div class="row g-2 mb-2">
                            @foreach($section['metrics'] as $label => $value)
                                <div class="col-6 col-md-3">
                                    <a href="{{ $meta['route'] }}?{{ http_build_query(['metric' => $label]) }}" class="text-decoration-none">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">{{ str($label)->replace('_', ' ')->title() }}</div>
                                            <div class="fw-semibold">{{ $value }}</div>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Item</th><th>Owner / Source</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    @forelse($section['items']->take(5) as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small">{{ $item['title'] }}</div>
                                                <div class="small text-muted">{{ $item['subtitle'] }}</div>
                                            </td>
                                            <td>
                                                <div class="small text-muted">Owner: course delivery team</div>
                                                <div class="small text-muted">Source: {{ $meta['label'] }}</div>
                                            </td>
                                            <td><span class="badge text-bg-light">{{ $item['status'] }}</span></td>
                                            <td class="text-end"><a href="{{ $item['action'] }}" class="btn btn-sm btn-outline-secondary">Go</a></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-muted text-center py-3">
                                                No current course-delivery exceptions for {{ $meta['label'] }}. Continue with assigned course load, published sessions, attendance intervention, engagement/material review, or mentor action checks if a source workflow is missing.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
